<?php
ob_start();
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    ob_clean(); echo json_encode(['error' => 'Not logged in.']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean(); echo json_encode(['error' => 'POST only.']); exit;
}

$raw     = file_get_contents('php://input');
$body    = json_decode($raw, true);
$message = trim($body['message'] ?? '');

if (empty($message)) {
    http_response_code(400);
    ob_clean(); echo json_encode(['error' => 'Empty message.']); exit;
}

// ── DB ────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=myfit_cal;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    ob_clean(); echo json_encode(['reply' => "Sorry, I can't connect to the database right now."]); exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$user_id = (int)$_SESSION['user_id'];

// ── Fetch user profile ────────────────────────────────────────
$ctx = [];
try {
    $s = $pdo->prepare("SELECT u.name, u.gender,
        ug.goal_type, ug.daily_calories, ug.daily_protein_g,
        uf.fitness_level, uf.activity_level, uf.days_per_week,
        up.height_cm, up.weight_kg, up.age
        FROM users u
        LEFT JOIN user_goals ug ON ug.user_id=u.id
        LEFT JOIN user_fitness uf ON uf.user_id=u.id
        LEFT JOIN user_profiles up ON up.user_id=u.id
        WHERE u.id=? LIMIT 1");
    $s->execute([$user_id]);
    $ctx = $s->fetch() ?: [];
} catch(Exception $e) {}

$workout_done = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(DISTINCT day_number) FROM user_workout_progress WHERE user_id=? AND completed=1");
    $s->execute([$user_id]);
    $workout_done = (int)$s->fetchColumn();
} catch(Exception $e) {}

// User info
$name       = explode(' ', $ctx['name'] ?? 'there')[0];
$gender     = strtolower($ctx['gender'] ?? 'male');
$goal       = $ctx['goal_type'] ?? 'maintain';
$cal_target = (int)($ctx['daily_calories'] ?? 2000);
$protein    = (int)($ctx['daily_protein_g'] ?? 150);
$level      = $ctx['fitness_level'] ?? 'beginner';
$days_pw    = (int)($ctx['days_per_week'] ?? 3);
$height     = $ctx['height_cm'] ?? null;
$weight     = $ctx['weight_kg'] ?? null;
$age        = $ctx['age'] ?? null;
$activity   = $ctx['activity_level'] ?? 'sedentary';
$is_female  = $gender === 'female';

$goal_labels = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
$goal_label  = $goal_labels[$goal] ?? 'Fitness';

// ── Smart Response Engine ─────────────────────────────────────
$msg = strtolower(trim($message));

function contains($msg, $keywords) {
    foreach ($keywords as $kw) {
        if (strpos($msg, $kw) !== false) return true;
    }
    return false;
}

$reply = '';

// ── GREETINGS ────────────────────────────────────────────────
if (contains($msg, ['hello','hi ','hey','kumusta','musta','good morning','good afternoon','good evening','magandang'])) {
    $hour = (int)date('H');
    $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $reply = "$greet, $name! I'm FitBot, your MyFitCal assistant.\n\nI can help you with:\n- Your calorie and meal plan\n- Workout tips and exercises\n- BMI and body metrics\n- Fitness goals and nutrition\n\nWhat would you like to know today?";
}

// ── CALORIES ─────────────────────────────────────────────────
elseif (contains($msg, ['calorie','calories','kcal','how much eat','how much should i eat','daily calories'])) {
    $deficit = $goal === 'lose' ? 'You are on a calorie deficit to help you lose weight.' : ($goal === 'gain' ? 'You are on a calorie surplus to support weight gain.' : ($goal === 'muscle' ? 'Your calories are set to support muscle building.' : 'Your calories are set for weight maintenance.'));
    $reply = "Your daily calorie target is $cal_target kcal, $name.\n\n$deficit\n\nBreakdown:\n- Breakfast: ".round($cal_target*0.25)." kcal\n- Lunch: ".round($cal_target*0.35)." kcal\n- Dinner: ".round($cal_target*0.30)." kcal\n- Snacks: ".round($cal_target*0.10)." kcal\n\nStick to this target daily for best results!";
}

// ── PROTEIN ──────────────────────────────────────────────────
elseif (contains($msg, ['protein','protina','how much protein'])) {
    $reply = "Your daily protein target is $protein grams, $name.\n\nGood protein sources:\n- Chicken breast (31g per 100g)\n- Eggs (6g per egg)\n- Tuna (30g per 100g)\n- Greek yogurt (10g per 100g)\n- Tofu (8g per 100g)\n\nTip: Spread your protein evenly across all meals for better muscle recovery and satiety.";
}

// ── MACROS ───────────────────────────────────────────────────
elseif (contains($msg, ['macro','macros','carbs','carbohydrate','fat','fats'])) {
    $carbs = round($cal_target * 0.45 / 4);
    $fat   = round($cal_target * 0.25 / 9);
    $reply = "Your recommended daily macros, $name:\n\n- Protein: {$protein}g\n- Carbohydrates: {$carbs}g\n- Fat: {$fat}g\n- Total: {$cal_target} kcal\n\nFor your $goal_label goal, prioritize protein to preserve muscle while managing your weight.";
}

// ── MEAL PLAN ────────────────────────────────────────────────
elseif (contains($msg, ['meal plan','meal','what to eat','what should i eat','pagkain','diet plan','food plan'])) {
    if ($goal === 'lose') {
        $reply = "For your Weight Loss goal, $name, here's what to focus on:\n\nBreakfast: Eggs + oatmeal or whole grain toast\nLunch: Grilled chicken + brown rice + vegetables\nDinner: Fish or lean meat + steamed veggies\nSnack: Fruit, Greek yogurt, or nuts\n\nAvoid: Sugary drinks, fried food, white bread, processed snacks\n\nRemember your daily target is $cal_target kcal. Check your Meals page for your personalized plan!";
    } elseif ($goal === 'gain') {
        $reply = "For your Weight Gain goal, $name, eat calorie-dense foods:\n\nBreakfast: Eggs + oatmeal + banana + peanut butter\nLunch: Chicken/beef + rice + beans\nDinner: Salmon or pork + rice + avocado\nSnack: Nuts, peanut butter sandwich, protein shake\n\nYour target is $cal_target kcal — try to eat every 3-4 hours. Check your Meals page for your full plan!";
    } elseif ($goal === 'muscle') {
        $reply = "For Muscle Gain, $name, protein is your priority:\n\nBreakfast: Eggs + oatmeal + milk\nLunch: Chicken breast + rice + vegetables\nPost-workout: Protein shake or eggs within 30 mins\nDinner: Lean beef or fish + sweet potato\nSnack: Greek yogurt, cottage cheese, or nuts\n\nTarget: $cal_target kcal with {$protein}g protein daily. Check your Meals page!";
    } else {
        $reply = "For Maintenance, $name, focus on balanced eating:\n\nBreakfast: Eggs or oatmeal\nLunch: Lean protein + whole grains + veggies\nDinner: Fish or chicken + vegetables\nSnack: Fruits or nuts\n\nYour daily target is $cal_target kcal. Check your Meals page for your personalized daily plan!";
    }
}

// ── BREAKFAST ────────────────────────────────────────────────
elseif (contains($msg, ['breakfast','almusal','morning food'])) {
    $reply = "Best breakfast options for your $goal_label goal, $name:\n\n- Scrambled eggs + whole wheat toast\n- Oatmeal with banana and peanut butter\n- Greek yogurt with fruits\n- Brown rice with egg and vegetables\n\nEat within 1-2 hours of waking up. Aim for ".round($cal_target*0.25)." kcal for breakfast.";
}

// ── LUNCH ────────────────────────────────────────────────────
elseif (contains($msg, ['lunch','tanghalian'])) {
    $reply = "Best lunch options for $name:\n\n- Grilled chicken + brown rice + vegetables\n- Tuna with whole grain bread + salad\n- Pork or beef stir-fry with rice\n- Tofu + vegetables + brown rice\n\nLunch should be your biggest meal — around ".round($cal_target*0.35)." kcal.";
}

// ── DINNER ───────────────────────────────────────────────────
elseif (contains($msg, ['dinner','hapunan','supper'])) {
    $reply = "Best dinner options for $name:\n\n- Steamed fish + vegetables\n- Chicken breast + salad\n- Lean beef + steamed broccoli\n- Grilled salmon + sweet potato\n\nKeep dinner light — around ".round($cal_target*0.30)." kcal. Avoid heavy carbs at night.";
}

// ── SNACKS ───────────────────────────────────────────────────
elseif (contains($msg, ['snack','meryenda','snacks'])) {
    $reply = "Healthy snack ideas for $name:\n\n- Banana or apple\n- Boiled eggs\n- Greek yogurt\n- Handful of nuts (almonds, walnuts)\n- Peanut butter on whole grain crackers\n- Cottage cheese\n\nSnacks should be around ".round($cal_target*0.10)." kcal. Avoid chips, candy, and sugary drinks!";
}

// ── WATER / HYDRATION ────────────────────────────────────────
elseif (contains($msg, ['water','hydration','tubig','drink'])) {
    $water = $weight ? round((float)$weight * 0.033, 1) : 2.5;
    $reply = "Daily water recommendation for $name:\n\n- Minimum: {$water}L per day".($weight ? " (based on your {$weight}kg weight)" : "")."\n- During workout: +500ml per hour of exercise\n- Hot weather: Add 500ml extra\n\nTips:\n- Drink a glass first thing in the morning\n- Drink a glass before each meal\n- Carry a water bottle at all times\n\nStaying hydrated helps with energy, metabolism, and muscle recovery!";
}

// ── WORKOUT ──────────────────────────────────────────────────
elseif (contains($msg, ['workout','exercise','training','ehersisyo','how to workout'])) {
    $plan_type = $is_female ? 'Female Plan (Glutes & Core focused)' : 'Male Plan (Full Body Strength)';
    $reply = "Your workout plan, $name:\n\n- Plan: $plan_type\n- Level: ".ucfirst($level)."\n- Days per week: $days_pw days\n- Days completed: $workout_done / 30\n\nTips for ".ucfirst($level)." level:\n"
        . ($level === 'beginner'
            ? "- Start slow, focus on proper form\n- Rest 60-90 seconds between sets\n- Don't skip rest days\n- Consistency beats intensity at this stage"
            : ($level === 'normal'
                ? "- Gradually increase reps each week\n- Add resistance or difficulty when exercises feel easy\n- Keep rest periods to 45-60 seconds\n- Track your progress"
                : "- Push for progressive overload every session\n- Reduce rest periods for more intensity\n- Mix compound and isolation exercises\n- Prioritize recovery and sleep"))
        ."\n\nGo to your Workout page to start today's session!";
}

// ── SPECIFIC EXERCISES ───────────────────────────────────────
elseif (contains($msg, ['push up','pushup','push-up'])) {
    $reply = "Push-up guide for $name:\n\nForm:\n1. Hands shoulder-width apart\n2. Body straight from head to heels\n3. Lower chest to near the floor\n4. Push back up, arms fully extended\n\nFor ".ucfirst($level).":\n"
        . ($level === 'beginner' ? "- Start with Knee Push-Ups: 3 sets x 8 reps" : ($level === 'normal' ? "- Regular Push-Ups: 3 sets x 15 reps" : "- Diamond or Clap Push-Ups: 4 sets x 20 reps"))
        ."\n\nCommon mistake: Don't let your hips sag or rise up!";
}

elseif (contains($msg, ['squat','squats'])) {
    $reply = "Squat guide for $name:\n\nForm:\n1. Feet shoulder-width apart, toes slightly out\n2. Chest up, back straight\n3. Lower until thighs are parallel to floor\n4. Drive through heels to stand up\n5. Squeeze glutes at the top\n\nFor ".ucfirst($level).":\n"
        . ($level === 'beginner' ? "- Chair Squat: 3 sets x 10 reps" : ($level === 'normal' ? "- Bodyweight Squat: 3 sets x 15 reps" : "- Jump Squat or Sumo Squat: 4 sets x 15 reps"))
        ."\n\nKeep knees aligned with toes — don't let them cave inward!";
}

elseif (contains($msg, ['plank'])) {
    $reply = "Plank guide for $name:\n\nForm:\n1. Forearms on floor, elbows under shoulders\n2. Body in a straight line from head to heels\n3. Tighten core, squeeze glutes\n4. Don't hold your breath — breathe steadily\n\nTargets: Core, shoulders, back\n\nFor ".ucfirst($level).":\n"
        . ($level === 'beginner' ? "- Hold 20-30 seconds, 3 sets" : ($level === 'normal' ? "- Hold 45-60 seconds, 3 sets" : "- Hold 90+ seconds, or try Plank Hip Dips"))
        ."\n\nProgression: Add 5 seconds each week!";
}

elseif (contains($msg, ['lunge','lunges'])) {
    $reply = "Lunge guide for $name:\n\nForm:\n1. Stand tall, step forward\n2. Lower back knee toward floor (don't touch)\n3. Front knee stays above ankle\n4. Push through front heel to return\n\nFor ".ucfirst($level).":\n"
        . ($level === 'beginner' ? "- Reverse Lunge: 3 sets x 10 each leg" : ($level === 'normal' ? "- Walking Lunge: 3 sets x 12 each leg" : "- Curtsy Lunge or Plyometric Lunge: 4 sets x 12"))
        ."\n\nGreat exercise for legs and glutes!";
}

// ── BMI ──────────────────────────────────────────────────────
elseif (contains($msg, ['bmi','body mass','weight status'])) {
    if ($height && $weight) {
        $bmi_val = round((float)$weight / pow((float)$height/100, 2), 1);
        $cat = $bmi_val < 18.5 ? 'Underweight' : ($bmi_val < 25 ? 'Normal weight' : ($bmi_val < 30 ? 'Overweight' : 'Obese'));
        $advice = $bmi_val < 18.5
            ? "You are underweight. Focus on increasing calorie intake with nutrient-dense foods."
            : ($bmi_val < 25
                ? "You are in the healthy range. Keep maintaining your current habits!"
                : ($bmi_val < 30
                    ? "You are slightly overweight. A calorie deficit and regular exercise will help."
                    : "Consider consulting a doctor for a personalized weight management plan."));
        $reply = "Your BMI, $name:\n\nBMI: $bmi_val\nCategory: $cat\nHeight: {$height}cm | Weight: {$weight}kg\n\n$advice\n\nHealthy BMI range: 18.5 - 24.9\n\nCheck the BMI Calculator page for more details!";
    } else {
        $reply = "BMI (Body Mass Index) measures your body weight relative to your height.\n\nHealthy ranges:\n- Underweight: below 18.5\n- Normal: 18.5 - 24.9\n- Overweight: 25 - 29.9\n- Obese: 30 and above\n\nUpdate your height and weight in your Profile to get your personal BMI!";
    }
}

// ── WEIGHT LOSS ──────────────────────────────────────────────
elseif (contains($msg, ['lose weight','weight loss','slim','payat','burn fat','cut'])) {
    $reply = "Weight loss tips for $name:\n\n1. Calorie deficit — eat $cal_target kcal daily (your current target)\n2. High protein — aim for {$protein}g per day to preserve muscle\n3. Cardio — add 20-30 mins of walking or jogging daily\n4. Complete your {$days_pw}-day/week workout plan\n5. Drink at least 2.5L of water daily\n6. Sleep 7-8 hours — poor sleep increases hunger hormones\n7. Avoid: sugary drinks, fried food, processed snacks\n\nAim for 0.5-1kg loss per week — slow and steady is sustainable!";
}

// ── WEIGHT GAIN / MUSCLE ─────────────────────────────────────
elseif (contains($msg, ['gain weight','weight gain','bulk','muscle','taba','mass'])) {
    $reply = "Weight/muscle gain tips for $name:\n\n1. Calorie surplus — eat $cal_target kcal daily (your current target)\n2. High protein — {$protein}g per day to build muscle\n3. Strength training — complete your workout plan consistently\n4. Eat every 3-4 hours — don't skip meals\n5. Calorie-dense foods: rice, sweet potato, eggs, nuts, avocado\n6. Post-workout meal within 30 minutes — protein + carbs\n7. Sleep 8+ hours — growth hormone releases during sleep\n\nAim for 0.25-0.5kg gain per week for lean bulk!";
}

// ── WORKOUT DAYS / PROGRESS ──────────────────────────────────
elseif (contains($msg, ['progress','how many days','days done','workout days','completed'])) {
    $remaining = 30 - $workout_done;
    $pct = round($workout_done / 30 * 100);
    $reply = "Your workout progress, $name:\n\n- Days completed: $workout_done / 30\n- Progress: $pct%\n- Days remaining: $remaining\n\n";
    if ($workout_done === 0) {
        $reply .= "You haven't started yet — go to your Workout page and begin today!";
    } elseif ($workout_done < 10) {
        $reply .= "Good start! Keep going — consistency is the key. Don't miss your next session!";
    } elseif ($workout_done < 20) {
        $reply .= "Great progress! You're almost halfway done. Keep pushing — you're doing amazing!";
    } elseif ($workout_done < 30) {
        $reply .= "Almost there! Only $remaining days left to complete your 30-day program. Finish strong!";
    } else {
        $reply .= "Congratulations! You completed the full 30-day program! That's a huge achievement!";
    }
}

// ── SLEEP / RECOVERY ─────────────────────────────────────────
elseif (contains($msg, ['sleep','recovery','rest','pahinga','tulog'])) {
    $reply = "Sleep and recovery tips for $name:\n\nSleep recommendation: 7-9 hours per night\n\nWhy it matters:\n- Muscles recover and grow during deep sleep\n- Growth hormone is released at night\n- Poor sleep increases hunger and cravings\n- Affects workout performance and energy\n\nRecovery tips:\n- Don't skip rest days in your workout plan\n- Stretch after every workout\n- Eat protein within 30 mins after exercise\n- Avoid screens 1 hour before bed\n\nRest is not weakness — it's part of the process!";
}

// ── MOTIVATION ───────────────────────────────────────────────
elseif (contains($msg, ['motivat','lazy','tired','give up','stuck','nawalan ng gana','wala nang gana','hindi na ko'])) {
    $quotes = [
        "Every workout counts, $name. Even a short session is better than none!",
        "You've already completed $workout_done days — that's real progress. Don't stop now!",
        "The hardest part is starting. Once you begin, you'll be glad you did, $name!",
        "Your goal is $goal_label and you're on track. Trust the process!",
        "Progress is progress, no matter how small. Keep showing up, $name!"
    ];
    $reply = $quotes[array_rand($quotes)]."\n\nRemember why you started:\n- Your goal: $goal_label\n- Your target: $cal_target kcal/day\n- Workout days done: $workout_done / 30\n\nYou've come this far — keep going!";
}

// ── REMINDER / SCHEDULE ──────────────────────────────────────
elseif (contains($msg, ['remind','schedule','when workout','what time','oras','reminder'])) {
    $reply = "Workout schedule tips for $name:\n\nYou're set to workout {$days_pw} days per week.\n\nBest times to workout:\n- Morning (6-8 AM): Boosts metabolism for the day\n- Lunch (12-1 PM): Great energy break\n- Evening (5-7 PM): Peak muscle strength\n\nYou can set your workout reminder in your Meals page after completing your meals!\n\nConsistency tip: Schedule your workout like a meeting — same time every day.";
}

// ── ABOUT MYFITCAL ───────────────────────────────────────────
elseif (contains($msg, ['myfitcal','about','system','app','what is this','ano ito','paano'])) {
    $reply = "About MyFitCal:\n\nMyFitCal is a Personalized Monitoring Program for Fitness Activities and Calorie Intake Management.\n\nFeatures:\n- Personalized 30-day workout plan\n- Daily meal plan based on your goals\n- Calorie and macro tracking\n- BMI calculator\n- Workout progress calendar\n- FitBot AI assistant (that's me!)\n- Workout reminders\n\nYour current setup:\n- Goal: $goal_label\n- Calories: $cal_target kcal/day\n- Workout: $days_pw days/week\n- Progress: $workout_done/30 days done";
}

// ── WHO ARE YOU ───────────────────────────────────────────────
elseif (contains($msg, ['who are you','what are you','sino ka','fitbot','are you ai','are you a bot'])) {
    $reply = "I'm FitBot, your personal fitness assistant inside MyFitCal!\n\nI can help you with:\n- Calorie and nutrition questions\n- Meal planning advice\n- Exercise guides and workout tips\n- BMI and body metrics\n- Fitness goals and motivation\n- How to use MyFitCal features\n\nI'm here to support your $goal_label journey, $name. What would you like to know?";
}

// ── THANKS ───────────────────────────────────────────────────
elseif (contains($msg, ['thank','thanks','salamat','ty '])) {
    $replies = [
        "You're welcome, $name! Keep pushing toward your $goal_label goal!",
        "Anytime, $name! Stay consistent and the results will come!",
        "Happy to help, $name! Don't forget to complete today's workout!"
    ];
    $reply = $replies[array_rand($replies)];
}

// ── GOODBYE ──────────────────────────────────────────────────
elseif (contains($msg, ['bye','goodbye','paalam','see you','ingat'])) {
    $reply = "Take care, $name! Stay consistent with your $goal_label plan. See you next time!";
}

// ── DEFAULT FALLBACK ─────────────────────────────────────────
else {
    $suggestions = [
        'How many calories should I eat?',
        'What should I eat for my goal?',
        'How is my workout progress?',
        'What is my BMI?',
        'Give me meal ideas',
        'How much water should I drink?',
    ];
    $reply = "I'm here to help with your fitness and nutrition, $name!\n\nHere are things I can answer:\n";
    foreach ($suggestions as $s) {
        $reply .= "- $s\n";
    }
    $reply .= "\nOr try asking about: calories, meals, workout, BMI, protein, water, sleep, or motivation!";
}

// ── Save to DB ────────────────────────────────────────────────
try {
    $pdo->prepare("INSERT INTO chat_messages (user_id, role, message) VALUES (?,?,?)")
        ->execute([$user_id, 'user', $message]);
    $pdo->prepare("INSERT INTO chat_messages (user_id, role, message) VALUES (?,?,?)")
        ->execute([$user_id, 'assistant', $reply]);
} catch(Exception $e) {}

ob_clean();
echo json_encode(['reply' => $reply]);