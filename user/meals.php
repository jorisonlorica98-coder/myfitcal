<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$db = getDB();
$day = (int)($_GET['day'] ?? 1);

$gq = $db->prepare("SELECT * FROM user_goals WHERE user_id=?");
$gq->execute([$user_id]);
$goals = $gq->fetch();

$fq = $db->prepare("SELECT * FROM user_fitness WHERE user_id=?");
$fq->execute([$user_id]);
$fitness = $fq->fetch();

$pq = $db->prepare("SELECT * FROM user_profiles WHERE user_id=?");
$pq->execute([$user_id]);
$profile = $pq->fetch();

$uq = $db->prepare("SELECT * FROM users WHERE id=?");
$uq->execute([$user_id]);
$user = $uq->fetch();
$is_female = strtolower($user['gender'] ?? 'male') === 'female';

$goal_type  = $goals['goal_type'] ?? 'maintain';
$cal_target = (int)($goals['daily_calories'] ?? 2000);
$pro_target = (int)($goals['daily_protein_g'] ?? 150);

$macro_splits = [
    'lose'     => ['carbs'=>0.40, 'protein'=>0.35, 'fat'=>0.25],
    'maintain' => ['carbs'=>0.45, 'protein'=>0.30, 'fat'=>0.25],
    'gain'     => ['carbs'=>0.50, 'protein'=>0.25, 'fat'=>0.25],
    'muscle'   => ['carbs'=>0.40, 'protein'=>0.40, 'fat'=>0.20],
];
$split = $macro_splits[$goal_type] ?? $macro_splits['maintain'];

$carbs_g   = round(($cal_target * $split['carbs']) / 4);
$protein_g = round(($cal_target * $split['protein']) / 4);
$fat_g     = round(($cal_target * $split['fat']) / 9);

$meals_plan = [
    // ══════════════════════════════════════════════════════════════════
    // LOSE
    // ══════════════════════════════════════════════════════════════════
    'lose' => [
        'breakfast' => [
            'name'=>'Breakfast','time'=>'7:00 AM','icon'=>'bi-sunrise','color'=>'#f97316','pct'=>0.25,
            'options' => [
                [
                    'label'=>'Lugaw + Itlog (Arroz Caldo)','img'=>'/myfitcal_system/meal.png/rice.png','budget'=>'ultra',
                    'foods' => [
                        ['name'=>'Lugaw/Arroz caldo','img'=>'/myfitcal_system/meal.png/Lugaw.png','calories'=>220,'protein'=>8,'carbs'=>42,'fat'=>3,'serving'=>'1.5 cups'],
                        ['name'=>'Boiled egg','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>70,'protein'=>6,'carbs'=>0,'fat'=>5,'serving'=>'1 large'],
                        ['name'=>'Sliced ginger (pampalasa)','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'3-4 slices'],
                        ['name'=>'Patis (fish sauce, konti)','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>10,'protein'=>1,'carbs'=>1,'fat'=>0,'serving'=>'1 tsp'],
                        ['name'=>'Mainit na tubig o black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
                [
                    'label'=>'Tinapay + Itlog Prito','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'ultra',
                    'foods' => [
                        ['name'=>'Tasty bread / monay','img'=>'/myfitcal_system/meal.png/bread.png','calories'=>180,'protein'=>5,'carbs'=>36,'fat'=>2,'serving'=>'2 slices o 2 monay'],
                        ['name'=>'Pritong itlog (sunny side up)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>90,'protein'=>6,'carbs'=>0,'fat'=>7,'serving'=>'1 itlog'],
                        ['name'=>'Kamatis at sibuyas (salad)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>1,'carbs'=>5,'fat'=>0,'serving'=>'1/2 cup hiniwang'],
                        ['name'=>'Toyo + calamansi sawsawan','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>10,'protein'=>1,'carbs'=>1,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'3-in-1 coffee o black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>55,'protein'=>1,'carbs'=>11,'fat'=>1,'serving'=>'1 sachet / 1 tasa'],
                    ],
                ],
                [
                    'label'=>'Pandesal + Egg Scramble','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Pandesal','img'=>'/myfitcal_system/meal.png/bandesal.png','calories'=>200,'protein'=>6,'carbs'=>38,'fat'=>3,'serving'=>'2 medium pieces'],
                        ['name'=>'Scrambled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>10,'carbs'=>1,'fat'=>10,'serving'=>'2 itlog, konting mantika'],
                        ['name'=>'Sliced tomato','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>20,'protein'=>1,'carbs'=>4,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Margarine / butter (konti)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>35,'protein'=>0,'carbs'=>0,'fat'=>4,'serving'=>'1/2 tsp'],
                        ['name'=>'Black coffee o green tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa, walang asukal'],
                    ],
                ],
                [
                    'label'=>'Oatmeal + Boiled Eggs','img'=>'/myfitcal_system/meal.png/oat.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Oatmeal (instant or rolled)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>5,'carbs'=>27,'fat'=>3,'serving'=>'1/2 cup dry'],
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>89,'protein'=>1,'carbs'=>23,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Boiled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>12,'carbs'=>1,'fat'=>10,'serving'=>'2 pinakuluang itlog'],
                        ['name'=>'Chia seeds (optional)','img'=>'/myfitcal_system/meal.png/pumpkin seed.png','calories'=>30,'protein'=>1,'carbs'=>3,'fat'=>2,'serving'=>'1/2 tbsp'],
                        ['name'=>'Black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
                [
                    'label'=>'Kamote + Itlog','img'=>'/myfitcal_system/meal.png/kamote.png','budget'=>'ultra',
                    'foods' => [
                        ['name'=>'Pinakuluang kamote','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>130,'protein'=>2,'carbs'=>30,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Pritong itlog','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>90,'protein'=>6,'carbs'=>0,'fat'=>7,'serving'=>'1 itlog'],
                        ['name'=>'Inasnan na kamatis (salted tomato)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>15,'protein'=>0,'carbs'=>3,'fat'=>0,'serving'=>'1 small'],
                        ['name'=>'Mainit na tubig o kape','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
                [
                    'label'=>'Greek Yogurt Parfait','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Low-fat Greek yogurt','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','calories'=>130,'protein'=>15,'carbs'=>9,'fat'=>3,'serving'=>'200g'],
                        ['name'=>'Mixed fruits (saging, mansanas)','img'=>'/myfitcal_system/meal.png/fruits.png','calories'=>80,'protein'=>1,'carbs'=>20,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Granola','img'=>'/myfitcal_system/meal.png/granola.png','calories'=>100,'protein'=>3,'carbs'=>18,'fat'=>3,'serving'=>'2 tbsp'],
                        ['name'=>'Honey (konti)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>30,'protein'=>0,'carbs'=>8,'fat'=>0,'serving'=>'1/2 tsp'],
                        ['name'=>'Black coffee o green tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
                [
                    'label'=>'Protein Shake + Banana','img'=>'/myfitcal_system/meal.png/shrimp.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Protein shake','img'=>'/myfitcal_system/meal.png/shrimp.png','calories'=>150,'protein'=>25,'carbs'=>6,'fat'=>3,'serving'=>'1 scoop + 250ml tubig'],
                        ['name'=>'Saging','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>89,'protein'=>1,'carbs'=>23,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Boiled egg','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>70,'protein'=>6,'carbs'=>0,'fat'=>5,'serving'=>'1 large'],
                        ['name'=>'Black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
            ],
        ],
        'lunch' => [
            'name'=>'Lunch','time'=>'12:00 PM','icon'=>'bi-sun','color'=>'#eab308','pct'=>0.35,
            'options' => [
                [
                    'label'=>'Grilled Chicken + Brown Rice','img'=>'/myfitcal_system/meal.png/brown.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Grilled chicken breast','img'=>'/myfitcal_system/meal.png/chicken-breast.png','calories'=>220,'protein'=>42,'carbs'=>0,'fat'=>5,'serving'=>'150g'],
                        ['name'=>'Brown rice','img'=>'/myfitcal_system/meal.png/brown.png','calories'=>220,'protein'=>5,'carbs'=>46,'fat'=>2,'serving'=>'1 cup cooked'],
                        ['name'=>'Steamed vegetables','img'=>'/myfitcal_system/meal.png/Steamed vegetables.png','calories'=>80,'protein'=>4,'carbs'=>15,'fat'=>1,'serving'=>'1.5 cups mixed'],
                        ['name'=>'Calamansi juice (no sugar)','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>15,'protein'=>0,'carbs'=>4,'fat'=>0,'serving'=>'1 glass'],
                        ['name'=>'Banana (dessert)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>89,'protein'=>1,'carbs'=>23,'fat'=>0,'serving'=>'1 medium'],
                    ],
                ],
                [
                    'label'=>'Tinolang Manok','img'=>'/myfitcal_system/meal.png/tinola.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Tinolang manok','img'=>'/myfitcal_system/meal.png/tinola.png','calories'=>230,'protein'=>28,'carbs'=>8,'fat'=>9,'serving'=>'1 bowl with 1 pc chicken'],
                        ['name'=>'Steamed white rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Dahon ng sili / malunggay (sa tinola)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>20,'protein'=>2,'carbs'=>3,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Patis sawsawan','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>10,'protein'=>1,'carbs'=>1,'fat'=>0,'serving'=>'1 tsp + calamansi'],
                        ['name'=>'Water with lemon','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Baked Fish + Quinoa','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Baked tilapia','img'=>'/myfitcal_system/meal.png/Baked tilapia.png','calories'=>180,'protein'=>35,'carbs'=>0,'fat'=>4,'serving'=>'150g'],
                        ['name'=>'Quinoa','img'=>'/myfitcal_system/meal.png/Quinoa.png','calories'=>185,'protein'=>7,'carbs'=>34,'fat'=>3,'serving'=>'1 cup cooked'],
                        ['name'=>'Mixed salad','img'=>'/myfitcal_system/meal.png/Mixed salad.png','calories'=>60,'protein'=>2,'carbs'=>10,'fat'=>2,'serving'=>'2 cups'],
                        ['name'=>'Olive oil + lemon dressing','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>45,'protein'=>0,'carbs'=>1,'fat'=>5,'serving'=>'1 tsp olive oil'],
                        ['name'=>'Water or unsweetened iced tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Tuna + Kamote','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Canned tuna in water','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>150,'protein'=>33,'carbs'=>0,'fat'=>1,'serving'=>'1 can (150g)'],
                        ['name'=>'Boiled sweet potato','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>130,'protein'=>2,'carbs'=>30,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Sauteed kangkong','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>50,'protein'=>3,'carbs'=>8,'fat'=>1,'serving'=>'1 cup'],
                        ['name'=>'Kamatis salad (with onion)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>1,'carbs'=>5,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1-2 glasses'],
                    ],
                ],
            ],
        ],
        'snack' => [
            'name'=>'Snack','time'=>'3:30 PM','icon'=>'bi-apple','color'=>'#22c55e','pct'=>0.10,
            'options' => [
                [
                    'label'=>'Apple + Greek Yogurt','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Apple','img'=>'/myfitcal_system/meal.png/Apple.png','calories'=>95,'protein'=>0,'carbs'=>25,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Low-fat Greek yogurt','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','calories'=>100,'protein'=>10,'carbs'=>7,'fat'=>3,'serving'=>'100g'],
                        ['name'=>'Cinnamon powder (optional)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 pinch'],
                    ],
                ],
                [
                    'label'=>'Banana + Peanut Butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Peanut butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>95,'protein'=>4,'carbs'=>3,'fat'=>8,'serving'=>'1 tbsp'],
                        ['name'=>'Water o unsweetened green tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Boiled Eggs + Cucumber','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Boiled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>12,'carbs'=>1,'fat'=>10,'serving'=>'2 large eggs'],
                        ['name'=>'Sliced cucumber','img'=>'/myfitcal_system/meal.png/Steamed vegetables.png','calories'=>15,'protein'=>1,'carbs'=>3,'fat'=>0,'serving'=>'1 cup sliced'],
                        ['name'=>'Pinch of salt + calamansi','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'to taste'],
                    ],
                ],
                [
                    'label'=>'Protein Bar + Fruit','img'=>'/myfitcal_system/meal.png/shrimp.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'High-protein bar','img'=>'/myfitcal_system/meal.png/shrimp.png','calories'=>180,'protein'=>20,'carbs'=>18,'fat'=>5,'serving'=>'1 bar (~60g)'],
                        ['name'=>'Apple o orange','img'=>'/myfitcal_system/meal.png/Apple.png','calories'=>70,'protein'=>0,'carbs'=>18,'fat'=>0,'serving'=>'1 small'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
        'dinner' => [
            'name'=>'Dinner','time'=>'7:00 PM','icon'=>'bi-moon-stars','color'=>'#6366f1','pct'=>0.30,
            'options' => [
                [
                    'label'=>'Baked Fish + Sweet Potato','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Baked fish fillet (tilapia o bangus)','img'=>'/myfitcal_system/meal.png/Baked tilapia.png','calories'=>180,'protein'=>35,'carbs'=>0,'fat'=>4,'serving'=>'150g'],
                        ['name'=>'Sweet potato','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>130,'protein'=>2,'carbs'=>30,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Fresh salad (lettuce, tomato, cucumber)','img'=>'/myfitcal_system/meal.png/Mixed salad.png','calories'=>40,'protein'=>1,'carbs'=>7,'fat'=>0,'serving'=>'2 cups greens'],
                        ['name'=>'Olive oil dressing (konti)','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>40,'protein'=>0,'carbs'=>0,'fat'=>4,'serving'=>'1 tsp'],
                        ['name'=>'Water o herbal tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Sinigang na Hipon','img'=>'/myfitcal_system/meal.png/shrimp.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Sinigang na hipon','img'=>'/myfitcal_system/meal.png/sinigang.png','calories'=>200,'protein'=>24,'carbs'=>10,'fat'=>6,'serving'=>'1 bowl with 5-6 shrimp'],
                        ['name'=>'Steamed rice (small)','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>150,'protein'=>3,'carbs'=>33,'fat'=>0,'serving'=>'3/4 cup cooked'],
                        ['name'=>'Kangkong / sitaw (sa sinigang)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>30,'protein'=>2,'carbs'=>5,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Patis + calamansi sawsawan','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>10,'protein'=>1,'carbs'=>1,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1-2 glasses'],
                    ],
                ],
                [
                    'label'=>'Chicken Breast + Broccoli','img'=>'/myfitcal_system/meal.png/chicken-breast.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Grilled chicken breast','img'=>'/myfitcal_system/meal.png/chicken-breast.png','calories'=>220,'protein'=>42,'carbs'=>0,'fat'=>5,'serving'=>'150g'],
                        ['name'=>'Steamed broccoli','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>55,'protein'=>4,'carbs'=>11,'fat'=>0,'serving'=>'1.5 cups'],
                        ['name'=>'Brown rice (small serving)','img'=>'/myfitcal_system/meal.png/brown.png','calories'=>150,'protein'=>3,'carbs'=>31,'fat'=>1,'serving'=>'3/4 cup cooked'],
                        ['name'=>'Garlic soy sauce (sawsawan)','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>15,'protein'=>1,'carbs'=>2,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Water o lemon water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Tofu Stir-Fry + Rice','img'=>'/myfitcal_system/meal.png/tofu.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Stir-fried tofu','img'=>'/myfitcal_system/meal.png/tofu.png','calories'=>180,'protein'=>16,'carbs'=>6,'fat'=>10,'serving'=>'150g firm tofu'],
                        ['name'=>'Mixed vegetables (carrots, beans, cabbage)','img'=>'/myfitcal_system/meal.png/Steamed vegetables.png','calories'=>70,'protein'=>3,'carbs'=>12,'fat'=>1,'serving'=>'1 cup'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Toyo + oyster sauce (konti)','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>20,'protein'=>1,'carbs'=>3,'fat'=>0,'serving'=>'1 tbsp mixed'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // GAIN
    // ══════════════════════════════════════════════════════════════════
    'gain' => [
        'breakfast' => [
            'name'=>'Breakfast','time'=>'7:00 AM','icon'=>'bi-sunrise','color'=>'#f97316','pct'=>0.25,
            'options' => [
                [
                    'label'=>'Eggs + Toast + Peanut Butter','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Whole eggs scrambled','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>280,'protein'=>20,'carbs'=>2,'fat'=>20,'serving'=>'4 eggs'],
                        ['name'=>'Whole wheat toast','img'=>'/myfitcal_system/meal.png/bread.png','calories'=>160,'protein'=>6,'carbs'=>30,'fat'=>2,'serving'=>'2 slices'],
                        ['name'=>'Peanut butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>190,'protein'=>8,'carbs'=>6,'fat'=>16,'serving'=>'2 tbsp'],
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 large'],
                        ['name'=>'Whole milk o fresh milk','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>8,'carbs'=>12,'fat'=>8,'serving'=>'1 glass (250ml)'],
                    ],
                ],
                [
                    'label'=>'Oatmeal with Nuts + Milk','img'=>'/myfitcal_system/meal.png/oat.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Oatmeal','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>300,'protein'=>10,'carbs'=>54,'fat'=>6,'serving'=>'1.5 cups cooked'],
                        ['name'=>'Mixed nuts (kasoy, mani, almonds)','img'=>'/myfitcal_system/meal.png/chickpeas.png','calories'=>160,'protein'=>5,'carbs'=>7,'fat'=>14,'serving'=>'1/4 cup'],
                        ['name'=>'Banana (sliced sa oatmeal)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Whole milk','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>8,'carbs'=>12,'fat'=>8,'serving'=>'1 glass (250ml)'],
                        ['name'=>'Boiled egg (sa tabi)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>70,'protein'=>6,'carbs'=>0,'fat'=>5,'serving'=>'1 large'],
                    ],
                ],
                [
                    'label'=>'Silog (Garlic Rice + Egg)','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Sinangag (garlic fried rice)','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>280,'protein'=>6,'carbs'=>55,'fat'=>6,'serving'=>'1.5 cups'],
                        ['name'=>'Fried eggs (sunny side up)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>180,'protein'=>12,'carbs'=>1,'fat'=>14,'serving'=>'2 eggs'],
                        ['name'=>'Tocino o longganisa','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>160,'protein'=>9,'carbs'=>8,'fat'=>10,'serving'=>'2 small pieces'],
                        ['name'=>'Atchara (pickled papaya)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>20,'protein'=>0,'carbs'=>5,'fat'=>0,'serving'=>'2 tbsp'],
                        ['name'=>'3-in-1 coffee o fresh orange juice','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>110,'protein'=>1,'carbs'=>26,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Protein Pancakes','img'=>'/myfitcal_system/meal.png/oat.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Oat protein pancakes','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>320,'protein'=>28,'carbs'=>35,'fat'=>8,'serving'=>'3 medium pancakes'],
                        ['name'=>'Banana slices (topping)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Honey drizzle','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>60,'protein'=>0,'carbs'=>16,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Scrambled eggs (sa tabi)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>10,'carbs'=>1,'fat'=>10,'serving'=>'2 eggs'],
                        ['name'=>'Fresh milk o orange juice','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>120,'protein'=>4,'carbs'=>24,'fat'=>2,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
        'lunch' => [
            'name'=>'Lunch','time'=>'12:00 PM','icon'=>'bi-sun','color'=>'#eab308','pct'=>0.35,
            'options' => [
                [
                    'label'=>'Beef Rice Bowl','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Lean beef (salpicao style)','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>350,'protein'=>32,'carbs'=>4,'fat'=>20,'serving'=>'200g'],
                        ['name'=>'White rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>260,'protein'=>5,'carbs'=>57,'fat'=>0,'serving'=>'1.5 cups cooked'],
                        ['name'=>'Sauteed mushrooms','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>40,'protein'=>2,'carbs'=>6,'fat'=>1,'serving'=>'1/2 cup'],
                        ['name'=>'Steamed broccoli','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>55,'protein'=>4,'carbs'=>11,'fat'=>0,'serving'=>'1 cup'],
                        ['name'=>'Whole milk','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>8,'carbs'=>12,'fat'=>8,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Adobong Manok + Rice','img'=>'/myfitcal_system/meal.png/Chicken-Thighs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Chicken adobo','img'=>'/myfitcal_system/meal.png/Chicken-Thighs.png','calories'=>380,'protein'=>34,'carbs'=>5,'fat'=>22,'serving'=>'2 pcs chicken'],
                        ['name'=>'Steamed white rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>260,'protein'=>5,'carbs'=>57,'fat'=>0,'serving'=>'1.5 cups'],
                        ['name'=>'Sauteed sitaw','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>60,'protein'=>3,'carbs'=>10,'fat'=>2,'serving'=>'1 cup'],
                        ['name'=>'Kamatis salad','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>1,'carbs'=>5,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Banana (dessert)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                    ],
                ],
                [
                    'label'=>'Pork Chop + Mashed Potato','img'=>'/myfitcal_system/meal.png/pork-chops.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Grilled pork chop','img'=>'/myfitcal_system/meal.png/pork-chops.png','calories'=>320,'protein'=>30,'carbs'=>0,'fat'=>20,'serving'=>'150g'],
                        ['name'=>'Mashed potato (with butter)','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>210,'protein'=>4,'carbs'=>32,'fat'=>8,'serving'=>'1 cup'],
                        ['name'=>'Steamed corn','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>130,'protein'=>4,'carbs'=>28,'fat'=>2,'serving'=>'1 ear'],
                        ['name'=>'Side salad (lettuce, tomato)','img'=>'/myfitcal_system/meal.png/Mixed salad.png','calories'=>35,'protein'=>1,'carbs'=>6,'fat'=>0,'serving'=>'1 cup'],
                        ['name'=>'Fresh milk o juice','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>120,'protein'=>4,'carbs'=>24,'fat'=>2,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Salmon + Brown Rice','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'premium',
                    'foods' => [
                        ['name'=>'Pan-seared salmon','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>350,'protein'=>40,'carbs'=>0,'fat'=>18,'serving'=>'200g fillet'],
                        ['name'=>'Brown rice','img'=>'/myfitcal_system/meal.png/brown.png','calories'=>220,'protein'=>5,'carbs'=>46,'fat'=>2,'serving'=>'1 cup cooked'],
                        ['name'=>'Steamed asparagus','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>40,'protein'=>3,'carbs'=>7,'fat'=>0,'serving'=>'1 cup'],
                        ['name'=>'Sauteed mushrooms','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>40,'protein'=>2,'carbs'=>6,'fat'=>1,'serving'=>'1/2 cup'],
                        ['name'=>'Lemon butter sauce (konti)','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>50,'protein'=>0,'carbs'=>1,'fat'=>5,'serving'=>'1 tsp'],
                    ],
                ],
            ],
        ],
        'snack' => [
            'name'=>'Snack','time'=>'3:30 PM','icon'=>'bi-apple','color'=>'#22c55e','pct'=>0.10,
            'options' => [
                [
                    'label'=>'Trail Mix + Protein Shake','img'=>'/myfitcal_system/meal.png/chickpeas.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Trail mix (nuts + raisins)','img'=>'/myfitcal_system/meal.png/chickpeas.png','calories'=>200,'protein'=>5,'carbs'=>22,'fat'=>12,'serving'=>'1/3 cup'],
                        ['name'=>'Protein shake','img'=>'/myfitcal_system/meal.png/shrimp.png','calories'=>160,'protein'=>20,'carbs'=>10,'fat'=>4,'serving'=>'1 scoop + 250ml water'],
                        ['name'=>'Banana (extra energy)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                    ],
                ],
                [
                    'label'=>'Bread + Peanut Butter + Banana','img'=>'/myfitcal_system/meal.png/penutbutter.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Whole wheat bread','img'=>'/myfitcal_system/meal.png/bread.png','calories'=>160,'protein'=>6,'carbs'=>30,'fat'=>2,'serving'=>'2 slices'],
                        ['name'=>'Peanut butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>190,'protein'=>8,'carbs'=>6,'fat'=>16,'serving'=>'2 tbsp'],
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Fresh milk','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>8,'carbs'=>12,'fat'=>8,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Boiled Eggs + Avocado','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Boiled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>12,'carbs'=>1,'fat'=>10,'serving'=>'2 large eggs'],
                        ['name'=>'Avocado (hiniwang)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>160,'protein'=>2,'carbs'=>9,'fat'=>15,'serving'=>'1/2 medium'],
                        ['name'=>'Whole wheat crackers','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>100,'protein'=>3,'carbs'=>18,'fat'=>3,'serving'=>'6 crackers'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Cottage Cheese + Crackers','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Cottage cheese','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','calories'=>110,'protein'=>14,'carbs'=>4,'fat'=>4,'serving'=>'100g'],
                        ['name'=>'Whole grain crackers','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>130,'protein'=>3,'carbs'=>22,'fat'=>4,'serving'=>'6-8 crackers'],
                        ['name'=>'Cherry tomatoes','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>1,'carbs'=>5,'fat'=>0,'serving'=>'8-10 pieces'],
                        ['name'=>'Fresh orange juice o water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>60,'protein'=>1,'carbs'=>14,'fat'=>0,'serving'=>'1 small glass'],
                    ],
                ],
            ],
        ],
        'dinner' => [
            'name'=>'Dinner','time'=>'7:00 PM','icon'=>'bi-moon-stars','color'=>'#6366f1','pct'=>0.30,
            'options' => [
                [
                    'label'=>'Chicken Thighs + White Rice','img'=>'/myfitcal_system/meal.png/Chicken-Thighs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Chicken thighs (skin on)','img'=>'/myfitcal_system/meal.png/Chicken-Thighs.png','calories'=>320,'protein'=>38,'carbs'=>0,'fat'=>18,'serving'=>'200g'],
                        ['name'=>'White rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>260,'protein'=>5,'carbs'=>57,'fat'=>0,'serving'=>'1.5 cups cooked'],
                        ['name'=>'Avocado (sa tabi)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>160,'protein'=>2,'carbs'=>9,'fat'=>15,'serving'=>'1/2 medium'],
                        ['name'=>'Sauteed veggies with oil','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>100,'protein'=>2,'carbs'=>10,'fat'=>6,'serving'=>'1 cup'],
                        ['name'=>'Mango o banana (dessert)','img'=>'/myfitcal_system/meal.png/fruits.png','calories'=>100,'protein'=>1,'carbs'=>25,'fat'=>0,'serving'=>'1 piece'],
                    ],
                ],
                [
                    'label'=>'Beef Steak + Garlic Rice','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'premium',
                    'foods' => [
                        ['name'=>'Lean beef steak','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>380,'protein'=>42,'carbs'=>0,'fat'=>20,'serving'=>'200g'],
                        ['name'=>'Garlic fried rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>260,'protein'=>5,'carbs'=>54,'fat'=>5,'serving'=>'1.5 cups'],
                        ['name'=>'Steamed broccoli','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>55,'protein'=>4,'carbs'=>11,'fat'=>0,'serving'=>'1.5 cups'],
                        ['name'=>'Mushroom gravy (konti)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>40,'protein'=>1,'carbs'=>5,'fat'=>2,'serving'=>'2 tbsp'],
                        ['name'=>'Fresh milk o juice','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>120,'protein'=>4,'carbs'=>24,'fat'=>2,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Pork Menudo + Rice','img'=>'/myfitcal_system/meal.png/pork-chops.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Pork menudo','img'=>'/myfitcal_system/meal.png/pork-chops.png','calories'=>350,'protein'=>25,'carbs'=>20,'fat'=>18,'serving'=>'1 cup'],
                        ['name'=>'Steamed white rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>260,'protein'=>5,'carbs'=>57,'fat'=>0,'serving'=>'1.5 cups'],
                        ['name'=>'Ensaladang kamatis at sibuyas','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>30,'protein'=>1,'carbs'=>6,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Banana (dessert)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                    ],
                ],
                [
                    'label'=>'Pasta with Meat Sauce','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Spaghetti with meat sauce','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>480,'protein'=>28,'carbs'=>62,'fat'=>14,'serving'=>'1.5 cups pasta + sauce'],
                        ['name'=>'Garlic bread','img'=>'/myfitcal_system/meal.png/bread.png','calories'=>100,'protein'=>3,'carbs'=>15,'fat'=>4,'serving'=>'1 slice'],
                        ['name'=>'Side salad','img'=>'/myfitcal_system/meal.png/Mixed salad.png','calories'=>40,'protein'=>1,'carbs'=>7,'fat'=>1,'serving'=>'1 cup'],
                        ['name'=>'Parmesan cheese (konti)','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','calories'=>55,'protein'=>5,'carbs'=>0,'fat'=>4,'serving'=>'1 tbsp grated'],
                        ['name'=>'Water o juice','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>60,'protein'=>1,'carbs'=>14,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // MAINTAIN
    // ══════════════════════════════════════════════════════════════════
    'maintain' => [
        'breakfast' => [
            'name'=>'Breakfast','time'=>'7:00 AM','icon'=>'bi-sunrise','color'=>'#f97316','pct'=>0.25,
            'options' => [
                [
                    'label'=>'Oatmeal + 2 Boiled Eggs','img'=>'/myfitcal_system/meal.png/oat.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Oatmeal with fruits','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>300,'protein'=>8,'carbs'=>55,'fat'=>5,'serving'=>'1 cup oats + berries'],
                        ['name'=>'2 boiled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>12,'carbs'=>1,'fat'=>10,'serving'=>'2 large'],
                        ['name'=>'Banana (sa oatmeal o sa tabi)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>89,'protein'=>1,'carbs'=>23,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Orange juice o fresh milk','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>110,'protein'=>2,'carbs'=>26,'fat'=>0,'serving'=>'1 glass (200ml)'],
                    ],
                ],
                [
                    'label'=>'Pandesal + Egg + Coffee','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Pandesal','img'=>'/myfitcal_system/meal.png/bandesal.png','calories'=>280,'protein'=>8,'carbs'=>52,'fat'=>4,'serving'=>'3 medium pieces'],
                        ['name'=>'Fried egg','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>90,'protein'=>6,'carbs'=>0,'fat'=>7,'serving'=>'1 large egg'],
                        ['name'=>'Margarine (panlamansin)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>35,'protein'=>0,'carbs'=>0,'fat'=>4,'serving'=>'1/2 tsp'],
                        ['name'=>'Sliced tomato','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>20,'protein'=>1,'carbs'=>4,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'3-in-1 coffee o black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>60,'protein'=>1,'carbs'=>12,'fat'=>1,'serving'=>'1 sachet / 1 cup'],
                    ],
                ],
                [
                    'label'=>'Smoothie Bowl','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Banana-berry smoothie base','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','calories'=>220,'protein'=>6,'carbs'=>48,'fat'=>2,'serving'=>'1 cup blended'],
                        ['name'=>'Granola topping','img'=>'/myfitcal_system/meal.png/granola.png','calories'=>120,'protein'=>3,'carbs'=>22,'fat'=>3,'serving'=>'3 tbsp'],
                        ['name'=>'Chia seeds','img'=>'/myfitcal_system/meal.png/pumpkin seed.png','calories'=>60,'protein'=>2,'carbs'=>6,'fat'=>4,'serving'=>'1 tbsp'],
                        ['name'=>'Sliced strawberries o mango','img'=>'/myfitcal_system/meal.png/fruits.png','calories'=>50,'protein'=>0,'carbs'=>13,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Black coffee o green tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
                [
                    'label'=>'Scrambled Eggs + Wheat Toast','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Scrambled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>200,'protein'=>14,'carbs'=>2,'fat'=>15,'serving'=>'3 eggs'],
                        ['name'=>'Whole wheat toast','img'=>'/myfitcal_system/meal.png/bread.png','calories'=>160,'protein'=>6,'carbs'=>30,'fat'=>2,'serving'=>'2 slices'],
                        ['name'=>'Sliced tomato + cucumber','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>1,'carbs'=>5,'fat'=>0,'serving'=>'1/2 cup each'],
                        ['name'=>'Butter o margarine (konti)','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>35,'protein'=>0,'carbs'=>0,'fat'=>4,'serving'=>'1/2 tsp'],
                        ['name'=>'Black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 cup'],
                    ],
                ],
            ],
        ],
        'lunch' => [
            'name'=>'Lunch','time'=>'12:00 PM','icon'=>'bi-sun','color'=>'#eab308','pct'=>0.35,
            'options' => [
                [
                    'label'=>'Chicken + Steamed Rice + Veggies','img'=>'/myfitcal_system/meal.png/chicken-breast.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Grilled chicken breast o fish','img'=>'/myfitcal_system/meal.png/chicken-breast.png','calories'=>220,'protein'=>40,'carbs'=>0,'fat'=>5,'serving'=>'150g'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Mixed veggies (steamed)','img'=>'/myfitcal_system/meal.png/Steamed vegetables.png','calories'=>80,'protein'=>3,'carbs'=>15,'fat'=>1,'serving'=>'1.5 cups'],
                        ['name'=>'Banana o apple (dessert)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>100,'protein'=>1,'carbs'=>25,'fat'=>0,'serving'=>'1 piece'],
                        ['name'=>'Water o calamansi juice','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>10,'protein'=>0,'carbs'=>3,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Pinakbet + Grilled Bangus','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Grilled bangus (milkfish)','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>220,'protein'=>30,'carbs'=>0,'fat'=>10,'serving'=>'150g'],
                        ['name'=>'Pinakbet (mixed vegetables)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>120,'protein'=>5,'carbs'=>18,'fat'=>4,'serving'=>'1 cup'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Bagoong (konting sawsawan)','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>15,'protein'=>1,'carbs'=>2,'fat'=>0,'serving'=>'1 tsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1-2 glasses'],
                    ],
                ],
                [
                    'label'=>'Beef Nilaga + Rice','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Beef nilaga soup','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>280,'protein'=>26,'carbs'=>14,'fat'=>12,'serving'=>'1 bowl'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Pechay / sitaw (sa nilaga)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>2,'carbs'=>4,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Toyo + calamansi sawsawan','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>10,'protein'=>1,'carbs'=>1,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1-2 glasses'],
                    ],
                ],
                [
                    'label'=>'Tuna Sandwich + Fruit','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Tuna salad sandwich','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>320,'protein'=>28,'carbs'=>30,'fat'=>10,'serving'=>'2 slices bread + tuna'],
                        ['name'=>'Sliced cucumber (sa loob o sa tabi)','img'=>'/myfitcal_system/meal.png/Steamed vegetables.png','calories'=>15,'protein'=>1,'carbs'=>3,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Apple o banana','img'=>'/myfitcal_system/meal.png/Apple.png','calories'=>100,'protein'=>1,'carbs'=>25,'fat'=>0,'serving'=>'1 piece'],
                        ['name'=>'Water o unsweetened iced tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
        'snack' => [
            'name'=>'Snack','time'=>'3:30 PM','icon'=>'bi-apple','color'=>'#22c55e','pct'=>0.10,
            'options' => [
                [
                    'label'=>'Greek Yogurt + Almonds','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Greek yogurt','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','calories'=>130,'protein'=>12,'carbs'=>9,'fat'=>4,'serving'=>'150g low-fat'],
                        ['name'=>'Handful of almonds','img'=>'/myfitcal_system/meal.png/chickpeas.png','calories'=>90,'protein'=>3,'carbs'=>3,'fat'=>8,'serving'=>'15 pieces'],
                        ['name'=>'Sliced strawberries o mango','img'=>'/myfitcal_system/meal.png/fruits.png','calories'=>50,'protein'=>0,'carbs'=>13,'fat'=>0,'serving'=>'1/2 cup'],
                    ],
                ],
                [
                    'label'=>'Banana + Peanut Butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Peanut butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>95,'protein'=>4,'carbs'=>3,'fat'=>8,'serving'=>'1 tbsp'],
                        ['name'=>'Water o unsweetened green tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Hard-Boiled Egg + Crackers','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Hard-boiled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>12,'carbs'=>1,'fat'=>10,'serving'=>'2 eggs'],
                        ['name'=>'Whole grain crackers','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>100,'protein'=>3,'carbs'=>18,'fat'=>3,'serving'=>'6 crackers'],
                        ['name'=>'Apple o pear','img'=>'/myfitcal_system/meal.png/Apple.png','calories'=>80,'protein'=>0,'carbs'=>20,'fat'=>0,'serving'=>'1 small'],
                    ],
                ],
                [
                    'label'=>'Mixed Nuts + Dried Fruits','img'=>'/myfitcal_system/meal.png/chickpeas.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Mixed nuts','img'=>'/myfitcal_system/meal.png/chickpeas.png','calories'=>160,'protein'=>5,'carbs'=>7,'fat'=>14,'serving'=>'1/4 cup'],
                        ['name'=>'Dried mango o raisins','img'=>'/myfitcal_system/meal.png/fruits.png','calories'=>70,'protein'=>0,'carbs'=>18,'fat'=>0,'serving'=>'2 tbsp'],
                        ['name'=>'Water o unsweetened juice','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>10,'protein'=>0,'carbs'=>3,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
        'dinner' => [
            'name'=>'Dinner','time'=>'7:00 PM','icon'=>'bi-moon-stars','color'=>'#6366f1','pct'=>0.30,
            'options' => [
                [
                    'label'=>'Baked Salmon + Quinoa','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'premium',
                    'foods' => [
                        ['name'=>'Baked salmon o tilapia','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>200,'protein'=>38,'carbs'=>0,'fat'=>6,'serving'=>'150g'],
                        ['name'=>'Quinoa o brown rice','img'=>'/myfitcal_system/meal.png/brown.png','calories'=>185,'protein'=>6,'carbs'=>37,'fat'=>2,'serving'=>'1 cup cooked'],
                        ['name'=>'Steamed broccoli','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>55,'protein'=>4,'carbs'=>11,'fat'=>0,'serving'=>'1.5 cups'],
                        ['name'=>'Lemon + herbs (pampalasa)','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>10,'protein'=>0,'carbs'=>2,'fat'=>0,'serving'=>'squeeze + pinch'],
                        ['name'=>'Water o herbal tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Chicken Sopas','img'=>'/myfitcal_system/meal.png/chicken-breast.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Chicken sopas','img'=>'/myfitcal_system/meal.png/chicken-breast.png','calories'=>320,'protein'=>24,'carbs'=>35,'fat'=>9,'serving'=>'1.5 cups with noodles'],
                        ['name'=>'Pandesal','img'=>'/myfitcal_system/meal.png/bandesal.png','calories'=>90,'protein'=>3,'carbs'=>18,'fat'=>1,'serving'=>'1 medium piece'],
                        ['name'=>'Kalabasa / carrots (sa sopas)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>30,'protein'=>1,'carbs'=>6,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Water o calamansi juice','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>10,'protein'=>0,'carbs'=>3,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Stir-Fried Tofu + Rice','img'=>'/myfitcal_system/meal.png/tofu.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Stir-fried tofu','img'=>'/myfitcal_system/meal.png/tofu.png','calories'=>180,'protein'=>16,'carbs'=>6,'fat'=>10,'serving'=>'150g firm tofu'],
                        ['name'=>'Mixed vegetables','img'=>'/myfitcal_system/meal.png/Steamed vegetables.png','calories'=>70,'protein'=>3,'carbs'=>12,'fat'=>1,'serving'=>'1 cup'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Toyo + garlic sauce','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>20,'protein'=>1,'carbs'=>3,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Pork Sinigang (Light)','img'=>'/myfitcal_system/meal.png/pork-chops.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Pork sinigang (lean cuts)','img'=>'/myfitcal_system/meal.png/pork-chops.png','calories'=>260,'protein'=>24,'carbs'=>12,'fat'=>12,'serving'=>'1 bowl'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Kangkong / sitaw (sa sinigang)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>30,'protein'=>2,'carbs'=>5,'fat'=>0,'serving'=>'1/2 cup'],
                        ['name'=>'Patis + calamansi sawsawan','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>10,'protein'=>1,'carbs'=>1,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1-2 glasses'],
                    ],
                ],
            ],
        ],
    ],

    // ══════════════════════════════════════════════════════════════════
    // MUSCLE
    // ══════════════════════════════════════════════════════════════════
    'muscle' => [
        'breakfast' => [
            'name'=>'Breakfast','time'=>'7:00 AM','icon'=>'bi-sunrise','color'=>'#f97316','pct'=>0.25,
            'options' => [
                [
                    'label'=>'Eggs + Oatmeal + Banana','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Whole eggs + egg whites','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>260,'protein'=>30,'carbs'=>2,'fat'=>14,'serving'=>'2 whole + 4 whites'],
                        ['name'=>'Oatmeal','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>5,'carbs'=>27,'fat'=>3,'serving'=>'1/2 cup dry'],
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Peanut butter (sa oatmeal)','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>95,'protein'=>4,'carbs'=>3,'fat'=>8,'serving'=>'1 tbsp'],
                        ['name'=>'Black coffee','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 cup'],
                    ],
                ],
                [
                    'label'=>'Protein Shake + Silog','img'=>'/myfitcal_system/meal.png/shrimp.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Protein shake','img'=>'/myfitcal_system/meal.png/shrimp.png','calories'=>150,'protein'=>25,'carbs'=>6,'fat'=>3,'serving'=>'1 scoop + 250ml milk'],
                        ['name'=>'Garlic fried rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>250,'protein'=>5,'carbs'=>52,'fat'=>5,'serving'=>'1.5 cups'],
                        ['name'=>'Fried eggs (sunny side up)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>180,'protein'=>12,'carbs'=>1,'fat'=>14,'serving'=>'2 eggs'],
                        ['name'=>'Tocino o longganisa','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>160,'protein'=>9,'carbs'=>8,'fat'=>10,'serving'=>'2 small pieces'],
                        ['name'=>'Banana (sa tabi)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                    ],
                ],
                [
                    'label'=>'Cottage Cheese Pancakes','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Cottage cheese pancakes','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','calories'=>280,'protein'=>28,'carbs'=>22,'fat'=>8,'serving'=>'3 medium pancakes'],
                        ['name'=>'Banana (topping)','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Honey drizzle','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>60,'protein'=>0,'carbs'=>16,'fat'=>0,'serving'=>'1 tbsp'],
                        ['name'=>'Scrambled eggs (sa tabi)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>10,'carbs'=>1,'fat'=>10,'serving'=>'2 eggs'],
                        ['name'=>'Fresh milk','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>150,'protein'=>8,'carbs'=>12,'fat'=>8,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'High-Protein Rice Bowl','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Scrambled eggs (4 eggs)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>280,'protein'=>24,'carbs'=>2,'fat'=>20,'serving'=>'4 large eggs'],
                        ['name'=>'Steamed rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup'],
                        ['name'=>'Canned tuna','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>100,'protein'=>22,'carbs'=>0,'fat'=>1,'serving'=>'100g drained'],
                        ['name'=>'Sliced tomato + toyo','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>25,'protein'=>1,'carbs'=>5,'fat'=>0,'serving'=>'1 medium + 1 tsp'],
                        ['name'=>'Black coffee o green tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 tasa'],
                    ],
                ],
            ],
        ],
        'lunch' => [
            'name'=>'Lunch','time'=>'12:00 PM','icon'=>'bi-sun','color'=>'#eab308','pct'=>0.35,
            'options' => [
                [
                    'label'=>'Grilled Chicken + Rice + Veggies','img'=>'/myfitcal_system/meal.png/chicken-breast.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Grilled chicken breast','img'=>'/myfitcal_system/meal.png/chicken-breast.png','calories'=>300,'protein'=>56,'carbs'=>0,'fat'=>7,'serving'=>'200g'],
                        ['name'=>'White rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>220,'protein'=>5,'carbs'=>46,'fat'=>2,'serving'=>'1 cup cooked'],
                        ['name'=>'Broccoli & sweet potato (steamed)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>180,'protein'=>5,'carbs'=>40,'fat'=>1,'serving'=>'1 cup each'],
                        ['name'=>'Boiled egg (dagdag protein)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>70,'protein'=>6,'carbs'=>0,'fat'=>5,'serving'=>'1 large'],
                        ['name'=>'Water o calamansi juice','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>10,'protein'=>0,'carbs'=>3,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Beef Tapa + Egg + Rice','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Beef tapa (marinated)','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>300,'protein'=>30,'carbs'=>8,'fat'=>16,'serving'=>'150g'],
                        ['name'=>'Fried egg','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>90,'protein'=>6,'carbs'=>0,'fat'=>7,'serving'=>'1 large'],
                        ['name'=>'Garlic fried rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>250,'protein'=>5,'carbs'=>52,'fat'=>5,'serving'=>'1.5 cups'],
                        ['name'=>'Atchara (pickled papaya)','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>20,'protein'=>0,'carbs'=>5,'fat'=>0,'serving'=>'2 tbsp'],
                        ['name'=>'Water o fresh juice','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>60,'protein'=>1,'carbs'=>14,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Tuna + Sweet Potato + Salad','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Canned tuna (2 cans)','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>200,'protein'=>44,'carbs'=>0,'fat'=>2,'serving'=>'2 cans (300g drained)'],
                        ['name'=>'Boiled sweet potato','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>130,'protein'=>2,'carbs'=>30,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Mixed salad with olive oil','img'=>'/myfitcal_system/meal.png/Mixed salad.png','calories'=>80,'protein'=>2,'carbs'=>10,'fat'=>5,'serving'=>'2 cups'],
                        ['name'=>'Boiled egg (extra protein)','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>70,'protein'=>6,'carbs'=>0,'fat'=>5,'serving'=>'1 large'],
                        ['name'=>'Water o lemon water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Salmon + Brown Rice + Asparagus','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'premium',
                    'foods' => [
                        ['name'=>'Pan-seared salmon','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>350,'protein'=>40,'carbs'=>0,'fat'=>18,'serving'=>'200g fillet'],
                        ['name'=>'Brown rice','img'=>'/myfitcal_system/meal.png/brown.png','calories'=>220,'protein'=>5,'carbs'=>46,'fat'=>2,'serving'=>'1 cup cooked'],
                        ['name'=>'Steamed asparagus','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>40,'protein'=>3,'carbs'=>7,'fat'=>0,'serving'=>'1 cup'],
                        ['name'=>'Side salad','img'=>'/myfitcal_system/meal.png/Mixed salad.png','calories'=>40,'protein'=>1,'carbs'=>7,'fat'=>1,'serving'=>'1 cup'],
                        ['name'=>'Water o lemon water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>5,'protein'=>0,'carbs'=>1,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
        'snack' => [
            'name'=>'Snack','time'=>'3:30 PM','icon'=>'bi-apple','color'=>'#22c55e','pct'=>0.10,
            'options' => [
                [
                    'label'=>'Protein Shake + Rice Cake','img'=>'/myfitcal_system/meal.png/shrimp.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Protein shake','img'=>'/myfitcal_system/meal.png/shrimp.png','calories'=>180,'protein'=>30,'carbs'=>8,'fat'=>3,'serving'=>'1 scoop + 300ml water'],
                        ['name'=>'Rice cake with peanut butter','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>140,'protein'=>5,'carbs'=>18,'fat'=>7,'serving'=>'2 cakes + 1 tbsp PB'],
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                    ],
                ],
                [
                    'label'=>'Eggs + Banana','img'=>'/myfitcal_system/meal.png/eggs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Boiled eggs','img'=>'/myfitcal_system/meal.png/eggs.png','calories'=>140,'protein'=>12,'carbs'=>1,'fat'=>10,'serving'=>'2 large eggs'],
                        ['name'=>'Banana','img'=>'/myfitcal_system/meal.png/bananas.png','calories'=>105,'protein'=>1,'carbs'=>27,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Peanut butter (optional dip)','img'=>'/myfitcal_system/meal.png/penutbutter.png','calories'=>95,'protein'=>4,'carbs'=>3,'fat'=>8,'serving'=>'1 tbsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Cottage Cheese + Pineapple','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Cottage cheese','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','calories'=>110,'protein'=>14,'carbs'=>4,'fat'=>4,'serving'=>'100g'],
                        ['name'=>'Fresh pineapple chunks','img'=>'/myfitcal_system/meal.png/fruits.png','calories'=>80,'protein'=>1,'carbs'=>20,'fat'=>0,'serving'=>'3/4 cup'],
                        ['name'=>'Mixed nuts (konti)','img'=>'/myfitcal_system/meal.png/chickpeas.png','calories'=>80,'protein'=>2,'carbs'=>4,'fat'=>7,'serving'=>'2 tbsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Nuts + Greek Yogurt','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Greek yogurt (high protein)','img'=>'/myfitcal_system/meal.png/Greek yogurt.png','calories'=>130,'protein'=>18,'carbs'=>6,'fat'=>3,'serving'=>'150g'],
                        ['name'=>'Mixed nuts','img'=>'/myfitcal_system/meal.png/chickpeas.png','calories'=>160,'protein'=>5,'carbs'=>7,'fat'=>14,'serving'=>'1/4 cup'],
                        ['name'=>'Honey drizzle','img'=>'/myfitcal_system/meal.png/oat.png','calories'=>30,'protein'=>0,'carbs'=>8,'fat'=>0,'serving'=>'1/2 tsp'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
        'dinner' => [
            'name'=>'Dinner','time'=>'7:00 PM','icon'=>'bi-moon-stars','color'=>'#6366f1','pct'=>0.30,
            'options' => [
                [
                    'label'=>'Lean Beef/Tuna + Sweet Potato','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Lean beef o tuna','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>280,'protein'=>45,'carbs'=>0,'fat'=>10,'serving'=>'200g'],
                        ['name'=>'Sweet potato','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>130,'protein'=>2,'carbs'=>30,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Spinach sauteed with olive oil','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>80,'protein'=>4,'carbs'=>6,'fat'=>5,'serving'=>'2 cups'],
                        ['name'=>'Cottage cheese (bago matulog)','img'=>'/myfitcal_system/meal.png/cottage-cheese.png','calories'=>110,'protein'=>14,'carbs'=>4,'fat'=>4,'serving'=>'100g'],
                        ['name'=>'Water o herbal tea','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Chicken Thighs + Kamote','img'=>'/myfitcal_system/meal.png/Chicken-Thighs.png','budget'=>'budget',
                    'foods' => [
                        ['name'=>'Baked chicken thighs','img'=>'/myfitcal_system/meal.png/Chicken-Thighs.png','calories'=>300,'protein'=>36,'carbs'=>0,'fat'=>16,'serving'=>'200g'],
                        ['name'=>'Boiled kamote (sweet potato)','img'=>'/myfitcal_system/meal.png/kamote.png','calories'=>130,'protein'=>2,'carbs'=>30,'fat'=>0,'serving'=>'1 medium'],
                        ['name'=>'Sauteed kangkong','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>50,'protein'=>3,'carbs'=>8,'fat'=>1,'serving'=>'1 cup'],
                        ['name'=>'Steamed white rice (maliit)','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>150,'protein'=>3,'carbs'=>33,'fat'=>0,'serving'=>'3/4 cup cooked'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1-2 glasses'],
                    ],
                ],
                [
                    'label'=>'Beef Stir-Fry + Rice','img'=>'/myfitcal_system/meal.png/lean-beef.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Lean beef strips stir-fry','img'=>'/myfitcal_system/meal.png/lean-beef.png','calories'=>300,'protein'=>35,'carbs'=>8,'fat'=>14,'serving'=>'180g beef + veggies'],
                        ['name'=>'White rice','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>200,'protein'=>4,'carbs'=>44,'fat'=>0,'serving'=>'1 cup cooked'],
                        ['name'=>'Broccoli florets','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>55,'protein'=>4,'carbs'=>11,'fat'=>0,'serving'=>'1.5 cups'],
                        ['name'=>'Toyo + oyster sauce + garlic','img'=>'/myfitcal_system/meal.png/coffee.png','calories'=>25,'protein'=>1,'carbs'=>4,'fat'=>0,'serving'=>'1 tbsp mixed'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
                [
                    'label'=>'Tuna Salad + Whole Grain Pasta','img'=>'/myfitcal_system/meal.png/tuna.png','budget'=>'mid',
                    'foods' => [
                        ['name'=>'Whole grain pasta','img'=>'/myfitcal_system/meal.png/rice.png','calories'=>220,'protein'=>8,'carbs'=>45,'fat'=>2,'serving'=>'1.5 cups cooked'],
                        ['name'=>'Canned tuna (2 cans)','img'=>'/myfitcal_system/meal.png/tuna.png','calories'=>200,'protein'=>44,'carbs'=>0,'fat'=>2,'serving'=>'300g drained'],
                        ['name'=>'Cherry tomatoes + cucumber','img'=>'/myfitcal_system/meal.png/Peas.png','calories'=>30,'protein'=>1,'carbs'=>6,'fat'=>0,'serving'=>'1/2 cup each'],
                        ['name'=>'Olive oil + lemon dressing','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>60,'protein'=>0,'carbs'=>2,'fat'=>6,'serving'=>'1 tbsp olive oil'],
                        ['name'=>'Water','img'=>'/myfitcal_system/meal.png/Water with lemon.png','calories'=>0,'protein'=>0,'carbs'=>0,'fat'=>0,'serving'=>'1 glass'],
                    ],
                ],
            ],
        ],
    ],
];

$meal_data = $meals_plan[$goal_type] ?? $meals_plan['maintain'];

// ── Handle option selection via SESSION ─────────────────────────────────────
$sess_opts_key = "meal_option_d{$day}";
if (!isset($_SESSION[$sess_opts_key])) $_SESSION[$sess_opts_key] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_option'])) {
    $meal_key   = $_POST['meal_key'] ?? '';
    $option_idx = (int)($_POST['option_idx'] ?? 0);
    $sess_key   = "meal_checks_d{$day}";
    if (!isset($_SESSION[$sess_key])) $_SESSION[$sess_key] = [];
    $_SESSION[$sess_key] = array_values(array_filter($_SESSION[$sess_key], fn($k) => !str_starts_with($k, $meal_key.'_')));
    $_SESSION[$sess_opts_key][$meal_key] = $option_idx;
    echo json_encode(['ok' => true, 'option_idx' => $option_idx]);
    exit;
}

$effective_meal_data = [];
foreach ($meal_data as $mk => $m) {
    $opt_idx = $_SESSION[$sess_opts_key][$mk] ?? 0;
    if (!isset($m['options'][$opt_idx])) $opt_idx = 0;
    $opt = $m['options'][$opt_idx];
    $effective_meal_data[$mk] = [
        'name'        => $m['name'],
        'time'        => $m['time'],
        'icon'        => $m['icon'],
        'color'       => $m['color'],
        'pct'         => $m['pct'],
        'img'         => $opt['img'],
        'label'       => $opt['label'],
        'foods'       => $opt['foods'],
        'options'     => $m['options'],
        'selected_opt'=> $opt_idx,
    ];
}

$plan_total = 0;
foreach ($effective_meal_data as $m) {
    foreach ($m['foods'] as $f) $plan_total += $f['calories'];
}
$scale = $plan_total > 0 ? $cal_target / $plan_total : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_food'])) {
    $meal_key  = $_POST['meal_key'] ?? '';
    $food_idx  = (int)($_POST['food_idx'] ?? 0);
    $food_name = trim($_POST['food_name'] ?? '');
    $food_cal  = (float)($_POST['food_cal'] ?? 0);
    $food_pro  = (float)($_POST['food_pro'] ?? 0);
    $food_carb = (float)($_POST['food_carb'] ?? 0);
    $food_fat  = (float)($_POST['food_fat'] ?? 0);
    $food_serv = trim($_POST['food_serv'] ?? '');
    $meal_type = trim($_POST['meal_type'] ?? 'snack');

    $sess_key = "meal_checks_d{$day}";
    if (!isset($_SESSION[$sess_key])) $_SESSION[$sess_key] = [];
    $k = $meal_key.'_'.$food_idx;

    if (in_array($k, $_SESSION[$sess_key])) {
        $_SESSION[$sess_key] = array_values(array_filter($_SESSION[$sess_key], fn($x)=>$x!==$k));
        $is_checked = false;
        try {
            $db->prepare("DELETE FROM calorie_logs WHERE user_id=? AND food_name=? AND meal_type=? AND log_date=CURDATE() LIMIT 1")
               ->execute([$user_id, $food_name, $meal_type]);
        } catch(Exception $e) {}
    } else {
        $_SESSION[$sess_key][] = $k;
        $is_checked = true;
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS calorie_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                food_name VARCHAR(150) NOT NULL,
                meal_type ENUM('breakfast','lunch','dinner','snack') DEFAULT NULL,
                calories DECIMAL(7,2) DEFAULT 0,
                protein_g DECIMAL(6,2) DEFAULT 0,
                carbs_g DECIMAL(6,2) DEFAULT 0,
                fat_g DECIMAL(6,2) DEFAULT 0,
                serving VARCHAR(60) DEFAULT NULL,
                log_date DATE NOT NULL DEFAULT (CURDATE()),
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user_date (user_id, log_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->prepare("INSERT INTO calorie_logs (user_id, food_name, meal_type, calories, protein_g, carbs_g, fat_g, serving, log_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())")
               ->execute([$user_id, $food_name, $meal_type, $food_cal, $food_pro, $food_carb, $food_fat, $food_serv]);
        } catch(Exception $e) {}
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS meal_compliance (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            log_date DATE NOT NULL,
            day_number INT NOT NULL DEFAULT 1,
            total_foods INT NOT NULL DEFAULT 0,
            checked_foods INT NOT NULL DEFAULT 0,
            is_complete TINYINT(1) DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_date (user_id, log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $checked_count = count($_SESSION[$sess_key] ?? []);
        $total_foods = 0;
        foreach ($effective_meal_data as $m) { $total_foods += count($m['foods']); }
        if ($total_foods === 0) $total_foods = 16;
        $db->prepare("INSERT INTO meal_compliance (user_id, log_date, day_number, total_foods, checked_foods, is_complete)
            VALUES (?,CURDATE(),?,?,?,?)
            ON DUPLICATE KEY UPDATE checked_foods=VALUES(checked_foods), total_foods=VALUES(total_foods), is_complete=VALUES(is_complete), updated_at=NOW()")
           ->execute([$user_id, $day, $total_foods, $checked_count, ($checked_count >= $total_foods) ? 1 : 0]);
    } catch(Exception $e) {}

    echo json_encode(['checked' => $is_checked]);
    exit;
}

$sess_key = "meal_checks_d{$day}";
$checked  = $_SESSION[$sess_key] ?? [];

$goal_labels = ['lose'=>'Weight Loss','maintain'=>'Maintenance','gain'=>'Weight Gain','muscle'=>'Muscle Gain'];
$goal_colors = ['lose'=>'#ef4444','maintain'=>'#2563eb','gain'=>'#16a34a','muscle'=>'#f97316'];
$gcolor = $goal_colors[$goal_type] ?? '#16a34a';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Day <?= $day ?> Meal Plan — MyFitCal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:#f5f5f4;color:#1c1917;min-height:100vh;}
.sidebar{position:fixed;left:0;top:0;bottom:0;width:220px;background:#1c1917;display:flex;flex-direction:column;z-index:200;}
.sb-top{padding:18px 14px 14px;border-bottom:1px solid rgba(255,255,255,.06);}
.sb-brand{display:flex;align-items:center;gap:9px;}
.sb-logo{width:30px;height:30px;border-radius:6px;overflow:hidden;flex-shrink:0;}
.sb-logo img{width:100%;height:100%;object-fit:contain;}
.sb-name{font-size:14px;font-weight:600;color:#fafaf9;}
.sb-plan{font-size:10px;color:#78716c;margin-top:1px;}
.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;}
.sb-lbl{font-size:10px;font-weight:600;color:#57534e;text-transform:uppercase;letter-spacing:.6px;padding:10px 6px 4px;display:block;}
.sb-link{display:flex;align-items:center;gap:9px;padding:7px 8px;border-radius:6px;font-size:13px;font-weight:500;color:#a8a29e;text-decoration:none;margin-bottom:1px;transition:all .12s;}
.sb-link:hover{background:rgba(255,255,255,.05);color:#e7e5e4;}
.sb-link.active{background:rgba(255,255,255,.08);color:#fafaf9;}
.sb-link i{font-size:14px;width:16px;text-align:center;}
.sb-foot{padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:9px;}
.sb-av{width:28px;height:28px;border-radius:50%;background:#292524;color:#e7e5e4;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:500;color:#e7e5e4;}
.sb-role{font-size:10px;color:#78716c;}
.sb-out{margin-left:auto;color:#57534e;text-decoration:none;font-size:15px;transition:color .12s;}
.sb-out:hover{color:#f87171;}
.main{margin-left:220px;min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#fff;border-bottom:1px solid #e7e5e4;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;width:100%;}
.topbar-l h2{font-size:14px;font-weight:600;color:#1c1917;}
.topbar-l p{font-size:12px;color:#78716c;margin-top:1px;}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:6px;border:1px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;color:#78716c;text-decoration:none;transition:all .12s;cursor:pointer;}
.tb-btn:hover{border-color:#1c1917;color:#1c1917;}
.content{padding:24px;flex:1;width:100%;max-width:100%;box-sizing:border-box;}
.hero{background:#1c1917;border-radius:8px;padding:20px 22px;margin-bottom:16px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:16px;}
.hero-left{max-width:70%;}
.hero-eyebrow{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#78716c;margin-bottom:6px;}
.hero-title{font-size:20px;font-weight:700;color:#fafaf9;margin-bottom:4px;}
.hero-sub{font-size:12px;color:#78716c;margin-bottom:10px;}
.goal-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;}
.hero-cal-badge{flex-shrink:0;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:12px 16px;text-align:center;}
.hcb-val{font-size:24px;font-weight:700;color:#fafaf9;line-height:1;}
.hcb-unit{font-size:10px;color:#16a34a;font-weight:600;text-transform:uppercase;letter-spacing:.5px;}
.hcb-label{font-size:10px;color:#57534e;margin-top:2px;text-transform:uppercase;letter-spacing:.5px;}
.macro-card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;padding:16px 18px;margin-bottom:12px;}
.sec-hd{display:flex;align-items:center;gap:6px;margin-bottom:12px;}
.sec-hd h3{font-size:13px;font-weight:600;color:#1c1917;}
.sec-dot{width:7px;height:7px;border-radius:50%;background:#16a34a;}
.macro-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px;}
.mg-item{border-radius:6px;padding:10px 8px;text-align:center;}
.mg-val{font-size:16px;font-weight:700;line-height:1;}
.mg-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-top:3px;color:#78716c;}
.macro-bars{display:flex;flex-direction:column;gap:7px;}
.mb-row{display:flex;align-items:center;gap:10px;}
.mb-label{font-size:11px;font-weight:600;width:50px;color:#78716c;}
.mb-track{flex:1;height:6px;background:#f5f5f4;border-radius:999px;overflow:hidden;}
.mb-fill{height:100%;border-radius:999px;}
.mb-pct{font-size:11px;font-weight:700;width:34px;text-align:right;}
.water-card{background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:12px;display:flex;align-items:center;gap:12px;}
.water-icon{width:36px;height:36px;border-radius:8px;background:rgba(37,99,235,.12);display:flex;align-items:center;justify-content:center;color:#2563eb;font-size:16px;flex-shrink:0;}
.wt-title{font-size:13px;font-weight:600;color:#1e40af;margin-bottom:2px;}
.wt-sub{font-size:11px;color:#3b82f6;line-height:1.5;}
.all-done-banner{display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:20px;text-align:center;margin-bottom:12px;}
.all-done-banner.show{display:block;}
.adb-icon{font-size:28px;color:#16a34a;margin-bottom:8px;}
.adb-title{font-size:15px;font-weight:700;color:#15803d;margin-bottom:3px;}
.adb-sub{font-size:12px;color:#16a34a;}
.next-workout-card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;overflow:hidden;margin-bottom:12px;}
.nwc-head{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid #f5f5f4;}
.nwc-icon{width:40px;height:40px;border-radius:8px;background:#1c1917;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;flex-shrink:0;}
.nwc-title{font-size:13px;font-weight:600;color:#1c1917;}
.nwc-sub{font-size:11px;color:#78716c;margin-top:2px;}
.nwc-time-section{padding:14px 18px;border-bottom:1px solid #f5f5f4;}
.nwc-time-label{font-size:12px;font-weight:600;color:#1c1917;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.nwc-time-label i{color:#16a34a;}
.nwc-time-pills{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;}
.time-pill{padding:5px 12px;border-radius:999px;border:1px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:500;color:#78716c;cursor:pointer;transition:all .12s;}
.time-pill:hover{border-color:#16a34a;color:#16a34a;}
.time-pill.selected{background:#1c1917;border-color:#1c1917;color:#fff;}
.custom-time-input{padding:6px 10px;border-radius:6px;border:1px solid #e7e5e4;font-family:'DM Sans',sans-serif;font-size:12px;color:#1c1917;outline:none;}
.custom-time-input:focus{border-color:#16a34a;}
.time-confirmed{display:flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 12px;font-size:12px;font-weight:500;color:#15803d;margin-top:8px;}
.nwc-actions{display:flex;gap:8px;padding:14px 18px;}
.nwc-btn-preview{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:9px;border-radius:6px;border:1px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;color:#1c1917;text-decoration:none;transition:all .12s;}
.nwc-btn-preview:hover{background:#f5f5f4;}
.nwc-btn-bot{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:9px;border-radius:6px;background:#1c1917;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;color:#fafaf9;text-decoration:none;transition:all .12s;border:none;}
.nwc-btn-bot:hover{background:#292524;color:#fafaf9;}
.meal-card{background:#fff;border-radius:8px;border:1px solid #e7e5e4;overflow:hidden;margin-bottom:10px;}
.option-picker-bar{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#fafaf9;border-bottom:1px solid #f0efee;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;}
.option-picker-bar::-webkit-scrollbar{display:none;}
.opt-pill{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;padding:5px 11px;border-radius:999px;border:1.5px solid #e7e5e4;background:#fff;font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;color:#78716c;cursor:pointer;transition:all .15s;flex-shrink:0;}
.opt-pill:hover{border-color:#1c1917;color:#1c1917;}
.opt-pill.active{background:#1c1917;border-color:#1c1917;color:#fff;}
.opt-pill .opt-budget{font-size:9px;font-weight:700;padding:1px 5px;border-radius:4px;text-transform:uppercase;letter-spacing:.04em;}
.opt-pill.active .opt-budget{background:rgba(255,255,255,.2);color:#fff;}
.opt-pill:not(.active) .opt-budget-budget{background:#dcfce7;color:#15803d;}
.opt-pill:not(.active) .opt-budget-mid{background:#fef9c3;color:#854d0e;}
.opt-pill:not(.active) .opt-budget-premium{background:#fce7f3;color:#9d174d;}
.picker-label{font-size:10px;font-weight:700;color:#a8a29e;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;flex-shrink:0;}
.meal-img-wrap{position:relative;overflow:hidden;}
.meal-img{width:100%;height:130px;object-fit:cover;display:block;transition:opacity .3s;}
.meal-img-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(28,25,23,.7) 0%,transparent 55%);}
.meal-img-info{position:absolute;bottom:10px;left:14px;right:14px;display:flex;align-items:flex-end;justify-content:space-between;}
.mi-name{font-size:15px;font-weight:700;color:#fff;}
.mi-time{font-size:11px;color:rgba(255,255,255,.65);display:flex;align-items:center;gap:4px;margin-top:2px;}
.mi-opt-label{font-size:11px;color:rgba(255,255,255,.8);margin-top:2px;display:flex;align-items:center;gap:4px;}
.mi-cal-badge{background:rgba(28,25,23,.6);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.15);color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:600;}
.meal-done-pill{position:absolute;top:10px;right:10px;display:none;align-items:center;gap:4px;background:#16a34a;color:#fff;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:600;}
.meal-done-pill.show{display:flex;}
.food-list{padding:4px 16px 10px;}
.food-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f5f5f4;transition:all .2s;}
.food-row:last-child{border-bottom:none;}
.food-row.food-done .fr-name{text-decoration:line-through;color:#a8a29e;}
.food-row.food-done .fr-serving{color:#d6d3d1;}
.food-row.food-done .fr-img{opacity:.35;filter:grayscale(.5);}
.food-row.food-done .fr-cal{color:#d6d3d1;}
.fr-check{flex-shrink:0;cursor:pointer;}
.check-circle{width:26px;height:26px;border-radius:50%;border:1.5px solid #d6d3d1;display:flex;align-items:center;justify-content:center;transition:all .18s;background:#fff;}
.check-circle:hover{border-color:#16a34a;background:#f0fdf4;}
.check-circle.checked{background:#1c1917;border-color:#1c1917;color:#fff;font-size:13px;}
.fr-img{width:52px;height:52px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f5f5f4;border:1px solid #e7e5e4;}
.fr-left{flex:1;min-width:0;}
.fr-name{font-size:13px;font-weight:600;color:#1c1917;margin-bottom:3px;}
.fr-serving{font-size:11px;color:#78716c;display:flex;align-items:center;gap:4px;margin-bottom:4px;}
.fr-macros{display:flex;gap:4px;flex-wrap:wrap;}
.fm-tag{font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;}
.fm-c{background:#fef9c3;color:#854d0e;}
.fm-p{background:#dcfce7;color:#15803d;}
.fm-f{background:#fce7f3;color:#9d174d;}
.fr-cal{font-size:13px;font-weight:700;color:#1c1917;white-space:nowrap;flex-shrink:0;}
.meal-total{display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#fafaf9;border-top:1px solid #f5f5f4;}
.mt-label{font-size:11px;font-weight:600;color:#78716c;}
.mt-macros{display:flex;gap:12px;}
.mt-item{font-size:11px;font-weight:700;}
.tip-card{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;margin-bottom:16px;}
.tip-head{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;}
.tip-text{font-size:12px;color:#166534;line-height:1.7;}
.btn-dashboard{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:11px;border:none;border-radius:6px;background:#1c1917;color:#fafaf9;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:background .12s;}
.btn-dashboard:hover{background:#292524;color:#fafaf9;}
@media(max-width:560px){.macro-grid{grid-template-columns:repeat(2,1fr);}.hero{flex-direction:column;}.hero-cal-badge{align-self:flex-start;}}

/* ── RESPONSIVE MOBILE ── */
.mob-bar{display:none;}
@media(max-width:768px){
  .mob-bar{
    display:flex;align-items:center;justify-content:space-between;
    position:fixed;top:0;left:0;right:0;z-index:200;
    background:#1c1917;padding:10px 16px;height:52px;
  }
  .mob-bar-brand{display:flex;align-items:center;gap:8px;}
  .mob-bar-brand img{width:26px;height:26px;border-radius:5px;object-fit:contain;}
  .mob-bar-brand span{font-size:14px;font-weight:600;color:#fafaf9;}
  .mob-hamburger{background:none;border:none;color:#fafaf9;font-size:20px;cursor:pointer;padding:4px;}

  .sidebar{
    transform:translateX(-100%);
    transition:transform .25s ease;
    z-index:300;width:240px;
  }
  .sidebar.open{transform:translateX(0);}

  .mob-overlay{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:250;
  }
  .mob-overlay.show{display:block;}

  .main{margin-left:0;padding-top:52px;}
  .topbar{display:none;}

  .stats{grid-template-columns:repeat(2,1fr);}
  .days-grid{grid-template-columns:repeat(6,1fr);}
  .ex-img{width:56px;height:56px;}
  .ex-name{font-size:12px;}
  .content{padding:16px;}
  .reminder-bar{flex-direction:column;align-items:flex-start;gap:8px;}
  .reminder-actions{width:100%;display:flex;justify-content:flex-end;}
  .today-head{flex-direction:column;align-items:flex-start;gap:6px;}
  .meal-card,.chat-wrap,.cal-wrap,.prof-wrap{max-width:100% !important;}
  table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;}

  /* Workout / exercise specific */
  .ex-grid,.exercise-grid{grid-template-columns:1fr !important;}
  .workout-layout{flex-direction:column !important;}
  .workout-sidebar{width:100% !important;position:relative !important;top:auto !important;}
}
@media(max-width:420px){
  .stats{grid-template-columns:1fr 1fr;}
  .greeting h1{font-size:18px;}
  .scard-val{font-size:20px;}
}

</style>
</head>
<body>
<!-- Mobile hamburger -->
<div class="mob-overlay" id="mobOverlay" onclick="closeSidebar()"></div>
<div class="mob-bar">
  <div class="mob-bar-brand">
    <img src="/myfitcal_system/assets/image/logo.png" alt="">
    <span>MyFitCal</span>
  </div>
  <button class="mob-hamburger" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
</div>
<script>
function toggleSidebar(){
  document.querySelector(".sidebar").classList.toggle("open");
  document.getElementById("mobOverlay").classList.toggle("show");
}
function closeSidebar(){
  document.querySelector(".sidebar").classList.remove("open");
  document.getElementById("mobOverlay").classList.remove("show");
}
</script>


<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-logo"><img src="/myfitcal_system/assets/image/logo.png" alt="MyFitCal"></div>
      <div>
        <div class="sb-name">MyFitCal</div>
        <div class="sb-plan"><?= $is_female ? 'Female Plan' : 'Male Plan' ?></div>
      </div>
    </div>
  </div>
  <nav class="sb-nav">
    <span class="sb-lbl">Main</span>
    <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="sb-link"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="/myfitcal_system/user/<?= $is_female ? 'workout_female' : 'workout' ?>.php?day=1" class="sb-link"><i class="bi bi-lightning-charge"></i> Workout</a>
    <a href="/myfitcal_system/user/meals.php" class="sb-link active"><i class="bi bi-egg-fried"></i> Meals</a>
    <span class="sb-lbl">Track</span>
    <a href="/myfitcal_system/user/calendar.php" class="sb-link"><i class="bi bi-calendar3"></i> Calendar</a>
    <a href="/myfitcal_system/user/chatbot.php" class="sb-link"><i class="bi bi-robot"></i> FitBot</a>
    <span class="sb-lbl">Account</span>
    <a href="/myfitcal_system/user/profile.php" class="sb-link"><i class="bi bi-person-circle"></i> My Profile</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-av"><?= strtoupper(substr($_SESSION['name'] ?? ($user['name'] ?? 'U'),0,1)) ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars(explode(' ', $_SESSION['name'] ?? ($user['name'] ?? 'User'))[0]) ?></div>
      <div class="sb-role">Member</div>
    </div>
    <a href="/myfitcal_system/logout.php" class="sb-out"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-l">
      <h2>Meals</h2>
      <p>Day <?= $day ?> of 30 &middot; <?= $goal_labels[$goal_type] ?></p>
    </div>
    <div>
      <a href="/myfitcal_system/user/<?= $is_female ? 'dashboard_female' : 'dashboard' ?>.php" class="tb-btn">
        <i class="bi bi-house"></i> Dashboard
      </a>
    </div>
  </div>

  <div class="content">

    <div class="hero">
      <div class="hero-left">
        <div class="hero-eyebrow"><i class="bi bi-calendar-day"></i> Day <?= $day ?> of 30</div>
        <div class="hero-title">Your Meal Plan</div>
        <div class="hero-sub">Choose your preferred meals for each slot below.</div>
        <div class="goal-pill" style="background:<?= $gcolor ?>22;color:<?= $gcolor ?>;border:1px solid <?= $gcolor ?>44;">
          <i class="bi bi-bullseye"></i> <?= $goal_labels[$goal_type] ?>
        </div>
      </div>
      <div class="hero-cal-badge">
        <div class="hcb-val"><?= number_format($cal_target) ?></div>
        <div class="hcb-unit">kcal</div>
        <div class="hcb-label">Daily Target</div>
      </div>
    </div>

    <div class="macro-card">
      <div class="sec-hd"><span class="sec-dot"></span><h3>Daily Macro Targets</h3></div>
      <div class="macro-grid">
        <div class="mg-item" style="background:#fff7ed;"><div class="mg-val" style="color:#f97316;"><?= number_format($cal_target) ?></div><div class="mg-label">Calories</div></div>
        <div class="mg-item" style="background:#fef9c3;"><div class="mg-val" style="color:#ca8a04;"><?= $carbs_g ?>g</div><div class="mg-label">Carbs</div></div>
        <div class="mg-item" style="background:#dcfce7;"><div class="mg-val" style="color:#16a34a;"><?= $protein_g ?>g</div><div class="mg-label">Protein</div></div>
        <div class="mg-item" style="background:#fce7f3;"><div class="mg-val" style="color:#db2777;"><?= $fat_g ?>g</div><div class="mg-label">Fat</div></div>
      </div>
      <div class="macro-bars">
        <div class="mb-row"><span class="mb-label">Carbs</span><div class="mb-track"><div class="mb-fill" style="width:<?= round($split['carbs']*100) ?>%;background:#eab308;"></div></div><span class="mb-pct" style="color:#ca8a04;"><?= round($split['carbs']*100) ?>%</span></div>
        <div class="mb-row"><span class="mb-label">Protein</span><div class="mb-track"><div class="mb-fill" style="width:<?= round($split['protein']*100) ?>%;background:#16a34a;"></div></div><span class="mb-pct" style="color:#16a34a;"><?= round($split['protein']*100) ?>%</span></div>
        <div class="mb-row"><span class="mb-label">Fat</span><div class="mb-track"><div class="mb-fill" style="width:<?= round($split['fat']*100) ?>%;background:#db2777;"></div></div><span class="mb-pct" style="color:#db2777;"><?= round($split['fat']*100) ?>%</span></div>
      </div>
    </div>

    <div class="water-card">
      <div class="water-icon"><i class="bi bi-droplet-fill"></i></div>
      <div>
        <div class="wt-title">Drink 8–10 glasses of water today</div>
        <div class="wt-sub">Staying hydrated helps muscle recovery and fat metabolism. Drink a glass before each meal.</div>
      </div>
    </div>

    <?php
    $all_meals_done = true;
    foreach ($effective_meal_data as $mk => $m) {
        foreach ($m['foods'] as $fi => $_f) {
            if (!in_array($mk.'_'.$fi, $checked)) { $all_meals_done = false; break 2; }
        }
    }

    $fq2 = $db->prepare("SELECT * FROM user_fitness WHERE user_id=?"); $fq2->execute([$user_id]); $fitness2 = $fq2->fetch();
    $genderq2 = $db->prepare("SELECT gender FROM users WHERE id=? LIMIT 1"); $genderq2->execute([$user_id]); $gender2 = $genderq2->fetchColumn() ?: 'male';
    $cq2 = $db->prepare("SELECT DISTINCT day_number FROM user_workout_progress WHERE user_id=? AND completed=1"); $cq2->execute([$user_id]); $comp2 = $cq2->fetchAll(PDO::FETCH_COLUMN);
    require_once '../config/exercises.php';
    $sched2 = getExercisePlan($fitness2['fitness_level']??'beginner',$fitness2['activity_level']??'sedentary',$goal_type,$fitness2['days_per_week']??3,$gender2);
    $next_wk = null;
    for($dx=1;$dx<=30;$dx++){if(!in_array($dx,$comp2)&&!$sched2[$dx]['is_rest']){$next_wk=$dx;break;}}
    $next_focus   = $next_wk ? $sched2[$next_wk]['focus'] : null;
    $next_excount = $next_wk ? count($sched2[$next_wk]['exercises']) : 0;
    $time_suggestions=['sedentary'=>['6:00 AM','7:00 AM','12:00 PM','6:00 PM','7:00 PM'],'lightly_active'=>['6:30 AM','7:30 AM','12:00 PM','5:30 PM','7:00 PM'],'moderately_active'=>['6:00 AM','12:00 PM','5:00 PM','6:00 PM'],'very_active'=>['5:30 AM','6:00 AM','5:00 PM','6:00 PM']];
    $activity_level=$fitness2['activity_level']??'sedentary';
    $suggested_times=$time_suggestions[$activity_level]??$time_suggestions['sedentary'];
    $saved_time=$_SESSION['preferred_workout_time']??'';
    if(!empty($_POST['save_workout_time'])){$_SESSION['preferred_workout_time']=$_POST['workout_time']??'';$saved_time=$_SESSION['preferred_workout_time'];}
    if(empty($saved_time)){try{$rq=$db->prepare("SELECT reminder_time FROM workout_reminders WHERE user_id=? AND is_active=1");$rq->execute([$user_id]);$saved_time=$rq->fetchColumn()?:'';if($saved_time)$_SESSION['preferred_workout_time']=$saved_time;}catch(Exception $e){}}
    ?>

    <div class="all-done-banner <?= $all_meals_done?'show':'' ?>" id="allDoneBanner">
      <div class="adb-icon"><i class="bi bi-patch-check-fill"></i></div>
      <div class="adb-title">All Meals Complete!</div>
      <div class="adb-sub">Great job following your meal plan today, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>!</div>
    </div>

    <?php if($all_meals_done && $next_wk): ?>
    <div class="next-workout-card" id="nextWorkoutCard">
      <div class="nwc-head">
        <div class="nwc-icon"><i class="bi bi-calendar-check-fill"></i></div>
        <div><div class="nwc-title">Next Workout — Day <?= $next_wk ?></div><div class="nwc-sub"><?= htmlspecialchars($next_focus) ?> &middot; <?= $next_excount ?> exercises</div></div>
      </div>
      <div class="nwc-time-section">
        <div class="nwc-time-label"><i class="bi bi-clock"></i> What time will you work out tomorrow?</div>
        <div class="nwc-time-pills">
          <?php foreach($suggested_times as $t): ?>
          <button class="time-pill <?= $saved_time===$t?'selected':'' ?>" onclick="selectTime('<?= $t ?>')"><?= $t ?></button>
          <?php endforeach; ?>
          <button class="time-pill <?= ($saved_time&&!in_array($saved_time,$suggested_times))?'selected':'' ?>" onclick="showCustomTime()">Custom</button>
        </div>
        <input type="time" id="customTimeInput" class="custom-time-input" style="display:none;" onchange="selectCustomTime(this.value)">
        <?php if($saved_time): ?>
        <div class="time-confirmed" id="timeConfirmed"><i class="bi bi-alarm-fill"></i> Reminder set for <strong><?= htmlspecialchars($saved_time) ?></strong> tomorrow!</div>
        <?php else: ?>
        <div class="time-confirmed" id="timeConfirmed" style="display:none;"></div>
        <?php endif; ?>
      </div>
      <div class="nwc-actions">
        <a href="/myfitcal_system/user/workout.php?day=<?= $next_wk ?>" class="nwc-btn-preview"><i class="bi bi-eye"></i> Preview Day <?= $next_wk ?></a>
        <a href="/myfitcal_system/user/chatbot.php" class="nwc-btn-bot"><i class="bi bi-robot"></i> Ask FitBot</a>
      </div>
    </div>
    <?php elseif($all_meals_done && !$next_wk): ?>
    <div class="next-workout-card">
      <div class="nwc-head">
        <div class="nwc-icon" style="background:#f97316;"><i class="bi bi-trophy-fill"></i></div>
        <div><div class="nwc-title">Program Complete!</div><div class="nwc-sub">You finished all 30 days — incredible achievement!</div></div>
      </div>
      <div class="nwc-actions"><a href="/myfitcal_system/user/chatbot.php" class="nwc-btn-bot" style="flex:1;"><i class="bi bi-robot"></i> Ask FitBot What's Next</a></div>
    </div>
    <?php endif; ?>

    <?php foreach($effective_meal_data as $meal_key => $meal):
      $meal_cal  = round(array_sum(array_column($meal['foods'],'calories')) * $scale);
      $meal_pro  = round(array_sum(array_column($meal['foods'],'protein')) * $scale);
      $meal_carb = round(array_sum(array_column($meal['foods'],'carbs')) * $scale);
      $meal_fat  = round(array_sum(array_column($meal['foods'],'fat')) * $scale);
      $meal_done_flag = true;
      foreach($meal['foods'] as $fi=>$_f){if(!in_array($meal_key.'_'.$fi,$checked)){$meal_done_flag=false;break;}}
    ?>
    <div class="meal-card" id="mcard-<?= $meal_key ?>">
      <div class="option-picker-bar">
        <span class="picker-label"><i class="bi bi-list-ul"></i> Options:</span>
        <?php foreach($meal['options'] as $oidx=>$opt):
          $btag=$opt['budget']??'budget';
          $isActive=($oidx===$meal['selected_opt']);
        ?>
        <button class="opt-pill <?= $isActive?'active':'' ?>" id="opt-<?= $meal_key ?>-<?= $oidx ?>" onclick="selectOption('<?= $meal_key ?>',<?= $oidx ?>,this)">
          <?= htmlspecialchars($opt['label']) ?>
          <?php if(!$isActive): ?><span class="opt-budget opt-budget-<?= $btag ?>"><?= $btag==='premium'?'💎':($btag==='mid'?'🟡':'💚') ?> <?= ucfirst($btag) ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>

      <div class="meal-img-wrap">
        <img src="<?= htmlspecialchars($meal['img']) ?>" alt="<?= $meal['name'] ?>" class="meal-img" id="meal-img-<?= $meal_key ?>">
        <div class="meal-img-overlay"></div>
        <div class="meal-img-info">
          <div>
            <div class="mi-name"><?= $meal['name'] ?></div>
            <div class="mi-time"><i class="bi bi-clock"></i> <?= $meal['time'] ?></div>
            <div class="mi-opt-label" id="meal-opt-label-<?= $meal_key ?>"><i class="bi bi-check2-circle"></i> <?= htmlspecialchars($meal['label']) ?></div>
          </div>
          <div class="mi-cal-badge" id="meal-cal-badge-<?= $meal_key ?>"><?= $meal_cal ?> kcal</div>
        </div>
        <div class="meal-done-pill <?= $meal_done_flag?'show':'' ?>" id="mealbadge-<?= $meal_key ?>">
          <i class="bi bi-check-circle-fill"></i> Done
        </div>
      </div>

      <div class="food-list" id="foods-<?= $meal_key ?>">
        <?php foreach($meal['foods'] as $fidx=>$food):
          $scaled_cal  = round($food['calories']*$scale);
          $scaled_pro  = round($food['protein']*$scale);
          $scaled_carb = round($food['carbs']*$scale);
          $scaled_fat  = round($food['fat']*$scale);
          $fkey        = $meal_key.'_'.$fidx;
          $is_checked  = in_array($fkey,$checked);
        ?>
        <div class="food-row <?= $is_checked?'food-done':'' ?>" id="frow-<?= $meal_key ?>-<?= $fidx ?>">
          <div class="fr-check" onclick="toggleFood('<?= $meal_key ?>',<?= $fidx ?>,<?= count($meal['foods']) ?>)"
            id="fcheck-<?= $meal_key ?>-<?= $fidx ?>"
            data-name="<?= htmlspecialchars($food['name'],ENT_QUOTES) ?>"
            data-cal="<?= $scaled_cal ?>" data-pro="<?= $scaled_pro ?>"
            data-carb="<?= $scaled_carb ?>" data-fat="<?= $scaled_fat ?>"
            data-serv="<?= htmlspecialchars($food['serving'],ENT_QUOTES) ?>"
            data-type="<?= $meal_key ?>">
            <?php if($is_checked): ?><div class="check-circle checked"><i class="bi bi-check-lg"></i></div>
            <?php else: ?><div class="check-circle"></div><?php endif; ?>
          </div>
          <?php if(!empty($food['img'])): ?>
          <img src="<?= htmlspecialchars($food['img']) ?>" alt="<?= htmlspecialchars($food['name']) ?>" class="fr-img">
          <?php endif; ?>
          <div class="fr-left">
            <div class="fr-name"><?= htmlspecialchars($food['name']) ?></div>
            <div class="fr-serving"><i class="bi bi-cup"></i> <?= htmlspecialchars($food['serving']) ?></div>
            <div class="fr-macros">
              <span class="fm-tag fm-c">C: <?= $scaled_carb ?>g</span>
              <span class="fm-tag fm-p">P: <?= $scaled_pro ?>g</span>
              <span class="fm-tag fm-f">F: <?= $scaled_fat ?>g</span>
            </div>
          </div>
          <div class="fr-cal"><?= $scaled_cal ?> kcal</div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="meal-total" id="meal-total-<?= $meal_key ?>">
        <span class="mt-label">Meal Total</span>
        <div class="mt-macros">
          <span class="mt-item" style="color:#ca8a04;">C: <?= $meal_carb ?>g</span>
          <span class="mt-item" style="color:#16a34a;">P: <?= $meal_pro ?>g</span>
          <span class="mt-item" style="color:#db2777;">F: <?= $meal_fat ?>g</span>
          <span class="mt-item" style="color:#1c1917;"><?= $meal_cal ?> kcal</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php
    $tips=['lose'=>'Avoid eating after 8 PM. Your last meal should be light and high in protein to prevent muscle loss overnight.','maintain'=>'Keep your meals consistent every day — same times, similar portions. Consistency is the key to maintaining weight.','gain'=>'If you struggle to eat enough, add healthy calorie-dense snacks like nuts, avocado, and peanut butter between meals.','muscle'=>'Eat your largest meal within 1–2 hours after your workout when your muscles absorb nutrients the best.'];
    ?>
    <div class="tip-card">
      <div class="tip-head"><i class="bi bi-lightbulb-fill"></i> Nutrition Tip — <?= $goal_labels[$goal_type] ?></div>
      <div class="tip-text"><?= $tips[$goal_type] ?></div>
    </div>

    <a href="/myfitcal_system/user/<?= $is_female?'dashboard_female':'dashboard' ?>.php" class="btn-dashboard">
      <i class="bi bi-house-fill"></i> Back to Dashboard
    </a>

  </div>
</div>

<script>
const mealOptionsData = <?php
  $jsData=[];
  foreach($meal_data as $mk=>$m){
    $jsData[$mk]=[];
    foreach($m['options'] as $oi=>$opt){
      $jsData[$mk][]=['label'=>$opt['label'],'img'=>$opt['img'],'budget'=>$opt['budget']??'budget','foods'=>array_map(function($f)use($scale){return['name'=>$f['name'],'img'=>$f['img'],'calories'=>round($f['calories']*$scale),'protein'=>round($f['protein']*$scale),'carbs'=>round($f['carbs']*$scale),'fat'=>round($f['fat']*$scale),'serving'=>$f['serving']];},$opt['foods'])];
    }
  }
  echo json_encode($jsData);
?>;

const currentDay = <?= $day ?>;

function selectOption(mealKey, optIdx, btn) {
  document.querySelectorAll('[id^="opt-' + mealKey + '-"]').forEach(p => {
    p.classList.remove('active');
    const bidx = parseInt(p.id.split('-').pop());
    const bdata = mealOptionsData[mealKey][bidx];
    const btag = bdata.budget || 'budget';
    const emoji = btag === 'premium' ? '💎' : (btag === 'mid' ? '🟡' : '💚');
    if (!p.querySelector('.opt-budget')) {
      const span = document.createElement('span');
      span.className = 'opt-budget opt-budget-' + btag;
      span.textContent = emoji + ' ' + btag.charAt(0).toUpperCase() + btag.slice(1);
      p.appendChild(span);
    }
  });
  btn.classList.add('active');
  const activeBadge = btn.querySelector('.opt-budget');
  if (activeBadge) activeBadge.remove();

  const opt = mealOptionsData[mealKey][optIdx];
  const img = document.getElementById('meal-img-' + mealKey);
  if (img) { img.style.opacity = '0.5'; img.src = opt.img; img.onload = () => { img.style.opacity = '1'; }; }
  const lbl = document.getElementById('meal-opt-label-' + mealKey);
  if (lbl) lbl.innerHTML = '<i class="bi bi-check2-circle"></i> ' + opt.label;

  const foodList = document.getElementById('foods-' + mealKey);
  if (foodList) {
    foodList.innerHTML = '';
    let totalCal = 0, totalPro = 0, totalCarb = 0, totalFat = 0;
    opt.foods.forEach((food, fidx) => {
      totalCal += food.calories; totalPro += food.protein; totalCarb += food.carbs; totalFat += food.fat;
      const row = document.createElement('div');
      row.className = 'food-row'; row.id = 'frow-' + mealKey + '-' + fidx;
      row.innerHTML = `
        <div class="fr-check" onclick="toggleFood('${mealKey}',${fidx},${opt.foods.length})"
          id="fcheck-${mealKey}-${fidx}"
          data-name="${food.name.replace(/"/g,'&quot;')}"
          data-cal="${food.calories}" data-pro="${food.protein}"
          data-carb="${food.carbs}" data-fat="${food.fat}"
          data-serv="${food.serving.replace(/"/g,'&quot;')}"
          data-type="${mealKey}">
          <div class="check-circle"></div>
        </div>
        <img src="${food.img}" alt="${food.name}" class="fr-img">
        <div class="fr-left">
          <div class="fr-name">${food.name}</div>
          <div class="fr-serving"><i class="bi bi-cup"></i> ${food.serving}</div>
          <div class="fr-macros">
            <span class="fm-tag fm-c">C: ${food.carbs}g</span>
            <span class="fm-tag fm-p">P: ${food.protein}g</span>
            <span class="fm-tag fm-f">F: ${food.fat}g</span>
          </div>
        </div>
        <div class="fr-cal">${food.calories} kcal</div>
      `;
      foodList.appendChild(row);
    });
    const tot = document.getElementById('meal-total-' + mealKey);
    if (tot) tot.querySelector('.mt-macros').innerHTML = `<span class="mt-item" style="color:#ca8a04;">C: ${totalCarb}g</span><span class="mt-item" style="color:#16a34a;">P: ${totalPro}g</span><span class="mt-item" style="color:#db2777;">F: ${totalFat}g</span><span class="mt-item" style="color:#1c1917;">${totalCal} kcal</span>`;
    const calBadge = document.getElementById('meal-cal-badge-' + mealKey);
    if (calBadge) calBadge.textContent = totalCal + ' kcal';
  }
  const badge = document.getElementById('mealbadge-' + mealKey);
  if (badge) badge.style.display = 'none';
  fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'select_option=1&meal_key='+encodeURIComponent(mealKey)+'&option_idx='+optIdx });
}

function toggleFood(mealKey, fidx, totalInMeal) {
    var row = document.getElementById('frow-' + mealKey + '-' + fidx);
    var el  = document.getElementById('fcheck-' + mealKey + '-' + fidx);
    var circ = el.querySelector('.check-circle');
    var params = 'toggle_food=1&meal_key=' + encodeURIComponent(mealKey) + '&food_idx=' + fidx;
    params += '&food_name=' + encodeURIComponent(el.dataset.name || '');
    params += '&food_cal='  + encodeURIComponent(el.dataset.cal  || 0);
    params += '&food_pro='  + encodeURIComponent(el.dataset.pro  || 0);
    params += '&food_carb=' + encodeURIComponent(el.dataset.carb || 0);
    params += '&food_fat='  + encodeURIComponent(el.dataset.fat  || 0);
    params += '&food_serv=' + encodeURIComponent(el.dataset.serv || '');
    params += '&meal_type=' + encodeURIComponent(el.dataset.type || 'snack');
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params })
    .then(r => r.json())
    .then(data => {
        if (data.checked) { circ.classList.add('checked'); circ.innerHTML = '<i class="bi bi-check-lg"></i>'; row.classList.add('food-done'); }
        else { circ.classList.remove('checked'); circ.innerHTML = ''; row.classList.remove('food-done'); }
        checkMealDone(mealKey, totalInMeal);
        checkAllDone();
    });
}

function checkMealDone(mealKey, total) {
    var done = document.querySelectorAll('#foods-' + mealKey + ' .check-circle.checked').length;
    var badge = document.getElementById('mealbadge-' + mealKey);
    if (badge) badge.style.display = done >= total ? 'flex' : 'none';
}

function checkAllDone() {
    var total   = document.querySelectorAll('.check-circle').length;
    var checked = document.querySelectorAll('.check-circle.checked').length;
    var banner  = document.getElementById('allDoneBanner');
    if (banner) {
        if (checked >= total) { banner.classList.add('show'); banner.scrollIntoView({behavior:'smooth',block:'center'}); }
        else { banner.classList.remove('show'); }
    }
}

function selectTime(t) {
  document.querySelectorAll('.time-pill').forEach(p => p.classList.remove('selected'));
  event.target.classList.add('selected');
  document.getElementById('customTimeInput').style.display = 'none';
  saveTime(t);
}
function showCustomTime() {
  document.getElementById('customTimeInput').style.display = 'inline-block';
  document.getElementById('customTimeInput').focus();
}
function selectCustomTime(val) {
  if (!val) return;
  const [h,m] = val.split(':');
  const hr = parseInt(h);
  const ampm = hr >= 12 ? 'PM' : 'AM';
  const hr12 = hr % 12 || 12;
  const label = hr12 + ':' + m + ' ' + ampm;
  document.querySelectorAll('.time-pill').forEach(p => p.classList.remove('selected'));
  event.target.classList.add('selected');
  saveTime(label);
}
function saveTime(t) {
  const nextDay = <?= $next_wk ?? 1 ?>;
  fetch('/myfitcal_system/user/set_reminder.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({time:t,day:nextDay}) })
  .then(r=>r.json()).then(data => {
    const el = document.getElementById('timeConfirmed');
    if (el) { el.innerHTML = '<i class="bi bi-alarm-fill"></i> Reminder saved — <strong>'+t+'</strong> tomorrow for Day '+nextDay+'!'; el.style.display = 'flex'; }
  });
}
</script>
</body>
</html>