<?php
// Exercise library — image from Unsplash, per level + activity
function getExercisePlan(string $level, string $activity, string $goal, int $days_per_week, string $gender = "male") {
    // Full exercise library
        // Local fallback images
        $imgPushup  = '/myfitcal_system/assets/image/workoutpushup.png';
        $imgRun     = '/myfitcal_system/assets/image/workoutrun.png';
        $imgLift    = '/myfitcal_system/assets/image/workoutlift.png';
        $imgYoga    = '/myfitcal_system/assets/image/workoutyoga.png';
        $imgRest    = '/myfitcal_system/assets/image/workoutrest.png';

        $library = [
            // === BEGINNER ===
            'beginner' => [
                'Jumping Jacks'       => ['img'=>$imgRun,   'sets'=>3,'reps'=>'20 reps','rest'=>45,'muscle'=>'Full Body','calories'=>8,'instructions'=>'Stand upright. Jump while spreading your legs and raising your arms overhead. Return to start. Keep a steady rhythm.'],
                'Wall Push-Up'        => ['img'=>$imgPushup,'sets'=>3,'reps'=>'12 reps','rest'=>45,'muscle'=>'Chest / Triceps','calories'=>5,'instructions'=>'Stand facing a wall. Place hands on wall at shoulder width. Bend elbows to bring chest toward wall, then push back. Keep body straight.'],
                'Chair Squat'         => ['img'=>$imgLift,  'sets'=>3,'reps'=>'15 reps','rest'=>45,'muscle'=>'Legs / Glutes','calories'=>7,'instructions'=>'Stand in front of a chair. Lower yourself as if sitting down, lightly touch the seat, then stand back up. Keep chest up and knees behind toes.'],
                'Standing March'      => ['img'=>$imgRun,   'sets'=>3,'reps'=>'30 reps','rest'=>30,'muscle'=>'Core / Cardio','calories'=>6,'instructions'=>'Stand tall and march in place, lifting knees to hip height alternately. Swing arms naturally. Keep your core tight.'],
                'Glute Bridge'        => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'15 reps','rest'=>45,'muscle'=>'Glutes / Hamstrings','calories'=>5,'instructions'=>'Lie on your back, knees bent, feet flat. Push hips up until body forms a straight line from shoulders to knees. Squeeze glutes at top, then lower.'],
                'Seated Leg Raise'    => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'12 reps each','rest'=>30,'muscle'=>'Core / Hip Flexors','calories'=>4,'instructions'=>'Sit on the edge of a chair. Hold the sides. Straighten one leg and raise it to hip height, hold 2 seconds, lower. Alternate legs.'],
                'Knee Push-Up'        => ['img'=>$imgPushup,'sets'=>3,'reps'=>'10 reps','rest'=>45,'muscle'=>'Chest / Arms','calories'=>5,'instructions'=>'Start in a push-up position with knees on the floor. Lower your chest to the floor, then push back up. Keep your back flat.'],
                'Standing Side Bend'  => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'15 each side','rest'=>30,'muscle'=>'Obliques','calories'=>3,'instructions'=>'Stand feet shoulder-width apart. Slide one hand down your leg as you bend sideways. Hold 2 seconds, return. Repeat other side.'],
            ],
            // === NORMAL ===
            'normal' => [
                'Push-Up'             => ['img'=>$imgPushup,'sets'=>3,'reps'=>'15 reps','rest'=>60,'muscle'=>'Chest / Triceps','calories'=>8,'instructions'=>'Start in plank position. Lower chest to floor keeping elbows at 45°. Push back up explosively. Keep core tight throughout.'],
                'Bodyweight Squat'    => ['img'=>$imgLift,  'sets'=>3,'reps'=>'20 reps','rest'=>60,'muscle'=>'Legs / Glutes','calories'=>10,'instructions'=>'Stand feet shoulder-width apart. Lower until thighs are parallel to floor. Drive through heels to stand. Keep chest up.'],
                'Plank'               => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'40 seconds','rest'=>45,'muscle'=>'Core','calories'=>6,'instructions'=>'Rest on forearms and toes. Keep body in a straight line from head to heels. Engage core and glutes. Do not let hips sag.'],
                'Reverse Lunge'       => ['img'=>$imgLift,  'sets'=>3,'reps'=>'12 each leg','rest'=>60,'muscle'=>'Legs / Balance','calories'=>9,'instructions'=>'Stand tall. Step one foot back and lower back knee toward floor. Front thigh should be parallel to floor. Push through front heel to return.'],
                'Tricep Dip'          => ['img'=>$imgPushup,'sets'=>3,'reps'=>'12 reps','rest'=>60,'muscle'=>'Triceps','calories'=>6,'instructions'=>'Sit on edge of chair, hands gripping front. Slide off and lower body by bending elbows to 90°. Push back up. Keep back close to chair.'],
                'Superman'            => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'15 reps','rest'=>45,'muscle'=>'Lower Back / Glutes','calories'=>5,'instructions'=>'Lie face down. Simultaneously lift arms, chest and legs off floor. Hold 2 seconds. Lower slowly. Squeeze glutes at top.'],
                'High Knees'          => ['img'=>$imgRun,   'sets'=>3,'reps'=>'30 seconds','rest'=>30,'muscle'=>'Cardio / Core','calories'=>12,'instructions'=>'Run in place, driving knees up to hip height with each step. Pump arms. Maintain a fast, consistent pace. Land softly on balls of feet.'],
                'Mountain Climber'    => ['img'=>$imgRun,   'sets'=>3,'reps'=>'20 reps each','rest'=>45,'muscle'=>'Core / Cardio','calories'=>11,'instructions'=>'Start in plank. Drive one knee toward chest, then quickly switch legs. Keep hips level and core engaged. Move at a controlled pace.'],
            ],
            // === EXPERT ===
            'expert' => [
                'Diamond Push-Up'     => ['img'=>$imgPushup,'sets'=>4,'reps'=>'15 reps','rest'=>60,'muscle'=>'Triceps / Chest','calories'=>10,'instructions'=>'Place hands together forming a diamond shape. Lower chest to hands keeping elbows close. Push up powerfully. Full range of motion.'],
                'Jump Squat'          => ['img'=>$imgLift,  'sets'=>4,'reps'=>'15 reps','rest'=>60,'muscle'=>'Legs / Power','calories'=>14,'instructions'=>'Squat down then explode upward into a jump. Land softly with bent knees and immediately go into next squat. Keep core tight.'],
                'Burpee'              => ['img'=>$imgRun,   'sets'=>4,'reps'=>'10 reps','rest'=>60,'muscle'=>'Full Body','calories'=>16,'instructions'=>'Stand, drop to squat, place hands down, jump feet back to plank, do a push-up, jump feet forward, then jump up with arms overhead.'],
                'Pike Push-Up'        => ['img'=>$imgPushup,'sets'=>4,'reps'=>'12 reps','rest'=>60,'muscle'=>'Shoulders','calories'=>8,'instructions'=>'Start in downward dog position. Bend elbows to lower head toward floor between hands. Push back up. Keep hips high throughout.'],
                'Bulgarian Split Squat'=> ['img'=>$imgLift, 'sets'=>4,'reps'=>'10 each','rest'=>75,'muscle'=>'Quads / Glutes','calories'=>12,'instructions'=>'Rear foot elevated on chair. Lower front knee toward floor keeping torso upright. Drive through front heel to stand. Full depth each rep.'],
                'Plank to Push-Up'    => ['img'=>$imgPushup,'sets'=>3,'reps'=>'10 reps','rest'=>60,'muscle'=>'Core / Chest','calories'=>10,'instructions'=>'Start in forearm plank. Push up to full plank one arm at a time. Lower back down one arm at a time. Alternate leading arm each rep.'],
                'Speed Skater'        => ['img'=>$imgRun,   'sets'=>4,'reps'=>'20 each','rest'=>45,'muscle'=>'Legs / Cardio','calories'=>13,'instructions'=>'Leap to the right landing on right foot, left foot behind. Swing arms like a speed skater. Immediately leap left. Move explosively.'],
                'V-Up'                => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'15 reps','rest'=>45,'muscle'=>'Core','calories'=>9,'instructions'=>'Lie flat. Simultaneously lift legs and upper body reaching hands toward feet. Form a V shape. Lower with control. Keep legs straight.'],
            ],
            // === ADVANCE ===
            'advance' => [
                'Clap Push-Up'        => ['img'=>$imgPushup,'sets'=>5,'reps'=>'12 reps','rest'=>60,'muscle'=>'Chest / Power','calories'=>14,'instructions'=>'Explode up from push-up position with enough force to clap hands. Land softly and immediately lower into next rep. Maximum power each rep.'],
                'Pistol Squat'        => ['img'=>$imgLift,  'sets'=>4,'reps'=>'8 each','rest'=>90,'muscle'=>'Legs / Balance','calories'=>15,'instructions'=>'Stand on one leg. Extend other leg forward. Lower into a full single-leg squat until hamstring touches calf. Drive up through heel.'],
                'Burpee Tuck Jump'    => ['img'=>$imgRun,   'sets'=>4,'reps'=>'10 reps','rest'=>75,'muscle'=>'Full Body','calories'=>20,'instructions'=>'Perform a burpee but at the top, tuck both knees to chest during the jump. Land softly and immediately start next rep.'],
                'Archer Push-Up'      => ['img'=>$imgPushup,'sets'=>4,'reps'=>'8 each','rest'=>75,'muscle'=>'Chest / Unilateral','calories'=>12,'instructions'=>'Wide push-up position. Shift weight to one arm bending it while other stays straight. Push back to center. Alternate sides.'],
                'Single Leg Deadlift' => ['img'=>$imgLift,  'sets'=>4,'reps'=>'10 each','rest'=>75,'muscle'=>'Hamstrings / Balance','calories'=>11,'instructions'=>'Stand on one leg. Hinge at hip lowering torso and raising rear leg until parallel to floor. Squeeze glute to return. Keep back flat.'],
                'Dragon Flag'         => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'6 reps','rest'=>90,'muscle'=>'Core','calories'=>13,'instructions'=>'Lie on bench gripping behind head. Raise entire body on shoulder blades. Lower legs slowly keeping body rigid. Do not touch floor until complete.'],
                'Lateral Bound'       => ['img'=>$imgRun,   'sets'=>4,'reps'=>'15 each','rest'=>45,'muscle'=>'Legs / Power','calories'=>16,'instructions'=>'Leap explosively to the side landing on one foot. Absorb impact by bending knee deeply. Immediately leap the other way. Maximum distance each jump.'],
                'L-Sit Hold'          => ['img'=>$imgYoga,  'sets'=>3,'reps'=>'20 seconds','rest'=>60,'muscle'=>'Core / Arms','calories'=>10,'instructions'=>'Sit on floor with hands beside hips. Press down to lift entire body. Extend legs forward parallel to floor. Hold. Keep shoulders depressed.'],
            ],
        ];

    // Pick exercises based on level
    // ===== FEMALE EXERCISE LIBRARY =====
    $female_library = [
        // === BEGINNER (Glutes, Core, Low Impact) ===
        'beginner' => [
            'Basic Glute Bridge'      => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'15 reps','rest'=>40,'muscle'=>'Glutes / Hamstrings','calories'=>5,'instructions'=>'Lie on back, knees bent, feet flat. Push hips up squeezing glutes. Hold 2 seconds at top, lower slowly. Keep core tight throughout.'],
            'Donkey Kick'             => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'12 reps each','rest'=>30,'muscle'=>'Glutes','calories'=>5,'instructions'=>'On hands and knees. Keep knee bent, kick one leg up toward ceiling squeezing glute at top. Lower with control. Keep hips level. Alternate legs.'],
            'Clamshell'               => ['img'=>'/myfitcal_system/workout.png/seated leg.png','sets'=>3,'reps'=>'15 reps each','rest'=>30,'muscle'=>'Hip Abductors / Glutes','calories'=>4,'instructions'=>'Lie on side, knees bent at 45 degrees, feet together. Lift top knee up like a clamshell without moving hips. Lower slowly. Repeat other side.'],
            'Wall Sit'                => ['img'=>'/myfitcal_system/workout.png/bodyweight squats.png','sets'=>3,'reps'=>'30 seconds','rest'=>45,'muscle'=>'Quads / Glutes','calories'=>6,'instructions'=>'Back flat against wall, slide down until thighs are parallel to floor. Hold position. Keep weight in heels and core tight throughout.'],
            'Hip Circle'              => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'10 each direction','rest'=>30,'muscle'=>'Hip Flexors / Glutes','calories'=>3,'instructions'=>'On hands and knees. Lift one knee and draw large circles with it — forward and backward. Keep core engaged and hips level. Alternate legs.'],
            'Bird Dog'                => ['img'=>'/myfitcal_system/workout.png/superman.png','sets'=>3,'reps'=>'10 reps each','rest'=>30,'muscle'=>'Core / Lower Back','calories'=>4,'instructions'=>'On hands and knees. Extend opposite arm and leg simultaneously. Hold 2 seconds. Return. Keep hips level and core engaged throughout.'],
            'Seated Hip Abduction'    => ['img'=>'/myfitcal_system/workout.png/seated leg.png','sets'=>3,'reps'=>'15 reps each','rest'=>30,'muscle'=>'Outer Thighs / Glutes','calories'=>4,'instructions'=>'Sit on edge of chair, feet flat. Place hands on outer thighs. Push knees outward against resistance of hands. Hold 2 seconds. Release. Great for outer glutes.'],
            'Lying Leg Raise'         => ['img'=>'/myfitcal_system/workout.png/seated leg.png','sets'=>3,'reps'=>'12 reps','rest'=>35,'muscle'=>'Lower Core / Hip Flexors','calories'=>5,'instructions'=>'Lie flat on back, hands under hips. Keeping legs straight, raise them to 90 degrees. Lower slowly without touching floor. Keep lower back pressed down.'],
        ],
        // === NORMAL (Glutes, Legs, Core Strength) ===
        'normal' => [
            'Single Leg Glute Bridge' => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'12 reps each','rest'=>40,'muscle'=>'Glutes / Hamstrings','calories'=>7,'instructions'=>'Lie on back, one knee bent foot flat, other leg straight. Drive hips up on one leg, squeezing glute hard. Lower with control. Switch legs.'],
            'Sumo Squat'              => ['img'=>'/myfitcal_system/workout.png/bodyweight squats.png','sets'=>3,'reps'=>'15 reps','rest'=>50,'muscle'=>'Inner Thighs / Glutes','calories'=>9,'instructions'=>'Stand feet wider than shoulder width, toes pointed out 45 degrees. Lower until thighs are parallel to floor. Drive through heels to stand. Squeeze glutes at top.'],
            'Hip Thrust'              => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>4,'reps'=>'15 reps','rest'=>50,'muscle'=>'Glutes','calories'=>8,'instructions'=>'Shoulders on bench edge, feet flat, knees bent. Lower hips then drive up explosively. Squeeze glutes hard at top. Full extension. Lower with control.'],
            'Fire Hydrant'            => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'15 reps each','rest'=>30,'muscle'=>'Hip Abductors / Glutes','calories'=>5,'instructions'=>'On hands and knees. Lift one knee out to the side keeping it bent at 90 degrees. Raise as high as possible without rotating hips. Lower slowly.'],
            'Lateral Leg Raise'       => ['img'=>'/myfitcal_system/workout.png/seated leg.png','sets'=>3,'reps'=>'15 reps each','rest'=>30,'muscle'=>'Outer Thighs / Glutes','calories'=>5,'instructions'=>'Lie on side, body in a straight line. Lift top leg to about 45 degrees. Hold 1 second, lower with control. Complete all reps then switch sides.'],
            'Plank Hip Dip'           => ['img'=>'/myfitcal_system/workout.png/planks.png','sets'=>3,'reps'=>'20 reps','rest'=>40,'muscle'=>'Obliques / Core','calories'=>7,'instructions'=>'Start in forearm plank. Rotate hips to dip one side toward floor without touching. Return to center, dip other side. Keep core tight throughout.'],
            'Frog Pump'               => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'20 reps','rest'=>35,'muscle'=>'Glutes','calories'=>6,'instructions'=>'Lie on back, soles of feet together, knees out wide. Drive hips up squeezing glutes hard at top. Lower slowly. Great for inner glute activation.'],
            'Curtsy Lunge'            => ['img'=>'/myfitcal_system/workout.png/reverse lunge.png','sets'=>3,'reps'=>'12 each','rest'=>50,'muscle'=>'Glutes / Inner Thighs','calories'=>9,'instructions'=>'Step one leg back and across behind other leg in curtsy position. Lower back knee toward floor. Return to start. Targets glutes from a unique angle.'],
        ],
        // === EXPERT (Power, Plyometric, Advanced Glutes) ===
        'expert' => [
            'Sumo Jump Squat'         => ['img'=>'/myfitcal_system/workout.png/jump squats.png','sets'=>4,'reps'=>'12 reps','rest'=>55,'muscle'=>'Inner Thighs / Glutes / Power','calories'=>14,'instructions'=>'Wide sumo stance, toes out. Squat down then explode upward into a jump. Land softly with bent knees back in sumo position. Squeeze glutes at top.'],
            'Hip Thrust Pulse'        => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>4,'reps'=>'20 pulses','rest'=>45,'muscle'=>'Glutes','calories'=>8,'instructions'=>'In hip thrust position. Drive hips up to full extension then pulse — small up and down movements at the top. Keep glutes squeezed the entire time.'],
            'Lateral Band Walk'       => ['img'=>'/myfitcal_system/workout.png/lateral bound.png','sets'=>3,'reps'=>'15 steps each','rest'=>40,'muscle'=>'Hip Abductors / Glutes','calories'=>7,'instructions'=>'Feet shoulder width, slight squat position. Step sideways keeping tension. Do not let feet come together. Keep chest up and core engaged. Return same way.'],
            'Reverse Hyper'           => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>3,'reps'=>'15 reps','rest'=>45,'muscle'=>'Glutes / Lower Back','calories'=>7,'instructions'=>'Lie face down on bench, hips at edge, legs hanging. Raise both legs until parallel to floor squeezing glutes. Lower slowly. Keep upper body still.'],
            'Diagonal Lunge'          => ['img'=>'/myfitcal_system/workout.png/reverse lunge.png','sets'=>3,'reps'=>'12 each direction','rest'=>50,'muscle'=>'Glutes / Legs','calories'=>10,'instructions'=>'Step diagonally forward-right, then lunge. Return to center. Step diagonally forward-left, lunge. Challenges glutes in multiple planes of motion.'],
            'Side Lying Hip Raise'    => ['img'=>'/myfitcal_system/workout.png/seated leg.png','sets'=>3,'reps'=>'12 each','rest'=>40,'muscle'=>'Obliques / Hip Abductors','calories'=>7,'instructions'=>'Lie on side, legs straight. Support on forearm. Raise hips off floor forming straight line. Hold 2 seconds. Lower. Keep body rigid throughout.'],
            'Glute Kickback'          => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>4,'reps'=>'15 each','rest'=>40,'muscle'=>'Glutes','calories'=>7,'instructions'=>'On hands and knees. Extend one leg straight back and up, squeezing glute at top. Lower with control. Keep hips squared to floor. Alternate legs.'],
            'Side Lunge Touchdown'    => ['img'=>'/myfitcal_system/workout.png/bodyweight squats.png','sets'=>3,'reps'=>'10 each','rest'=>50,'muscle'=>'Inner Thighs / Glutes','calories'=>10,'instructions'=>'Step wide to one side, bend that knee deep, touch floor with opposite hand. Push back to center explosively. Challenges balance and inner thigh strength.'],
        ],
        // === ADVANCE (Elite Female Strength) ===
        'advance' => [
            'Single Leg Squat'        => ['img'=>'/myfitcal_system/workout.png/pistol squats.png','sets'=>4,'reps'=>'8 each','rest'=>75,'muscle'=>'Quads / Glutes / Balance','calories'=>15,'instructions'=>'Stand on one leg. Extend other leg forward. Lower into full single-leg squat until hamstring touches calf. Drive up through heel. Ultimate leg strength.'],
            'Nordic Hamstring Curl'   => ['img'=>'/myfitcal_system/workout.png/single leg.png','sets'=>4,'reps'=>'6 reps','rest'=>90,'muscle'=>'Hamstrings / Glutes','calories'=>12,'instructions'=>'Kneel, feet anchored. Lean forward slowly lowering torso toward floor using hamstrings as brake. Push back up with hands. Extremely challenging for posterior chain.'],
            'Plyometric Curtsy Lunge' => ['img'=>'/myfitcal_system/workout.png/jump squats.png','sets'=>4,'reps'=>'10 each','rest'=>60,'muscle'=>'Glutes / Power','calories'=>15,'instructions'=>'Curtsy lunge down then explode up switching legs in mid-air. Land in opposite curtsy position. Explosive glute power exercise. Keep torso upright.'],
            'Single Leg Hip Thrust'   => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>4,'reps'=>'10 each','rest'=>70,'muscle'=>'Glutes / Unilateral','calories'=>12,'instructions'=>'Hip thrust position but one leg extended. Drive through single leg to full hip extension. Maximum glute squeeze at top. Lower with control. Most advanced glute exercise.'],
            'Power Lateral Bound'     => ['img'=>'/myfitcal_system/workout.png/lateral bound.png','sets'=>4,'reps'=>'15 each','rest'=>45,'muscle'=>'Glutes / Cardio / Power','calories'=>16,'instructions'=>'Leap explosively to side landing on one foot. Absorb impact by bending knee deeply. Immediately leap the other way. Maximum power and distance each jump.'],
            'Ab Dragon Flag'          => ['img'=>'/myfitcal_system/workout.png/dragon flag.png','sets'=>3,'reps'=>'6 reps','rest'=>90,'muscle'=>'Core / Strength','calories'=>13,'instructions'=>'Lie on bench gripping behind head. Raise entire body on shoulder blades. Lower legs slowly keeping body rigid. Do not touch floor until complete.'],
            'Plyometric Hip Thrust'   => ['img'=>'/myfitcal_system/workout.png/glute bridge.png','sets'=>4,'reps'=>'10 reps','rest'=>60,'muscle'=>'Glutes / Power','calories'=>14,'instructions'=>'Hip thrust position. Drive hips up explosively so hips leave bench at top. Land softly and immediately into next rep. Maximum glute power development.'],
            'L-Sit Core Hold'         => ['img'=>'/myfitcal_system/workout.png/L sit.png','sets'=>3,'reps'=>'20 seconds','rest'=>60,'muscle'=>'Core / Hip Flexors','calories'=>10,'instructions'=>'Sit on floor hands beside hips. Press down to lift entire body off floor. Extend legs forward parallel to floor. Hold. Keep shoulders depressed and core tight.'],
        ],
    ];

    $female_focuses = [
        'lose'     => ['Cardio & Core','Lower Body','Full Body','Cardio & Core','Glutes & Legs','Rest Day'],
        'maintain' => ['Lower Body','Core & Arms','Glutes & Legs','Full Body','Core & Arms','Rest Day'],
        'gain'     => ['Glutes & Legs','Upper Body','Rest Day','Glutes & Legs','Full Body','Core'],
        'muscle'   => ['Glutes','Hamstrings & Core','Legs & Cardio','Upper Body & Core','Rest Day','Full Body'],
    ];

    // Choose library based on gender
    if (strtolower($gender) === 'female') {
        $pool = $female_library[$level] ?? $female_library['beginner'];
        $focus_cycle = $female_focuses[$goal] ?? $female_focuses['maintain'];
    } else {
        $pool = $library[$level] ?? $library['beginner'];
    }
    $all  = array_keys($pool);

    // Build 30-day schedule
    $schedule = [];
    $day_count = 0;
    $workout_day = 0;

    // Focus rotation by goal (male)
    if (strtolower($gender) !== 'female') {
        $focuses = [
            'lose'     => ['Cardio Burn','Full Body','HIIT','Cardio Burn','Full Body','Rest Day'],
            'maintain' => ['Full Body','Strength','Cardio','Full Body','Strength','Rest Day'],
            'gain'     => ['Upper Body','Lower Body','Rest Day','Upper Body','Lower Body','Full Body'],
            'muscle'   => ['Chest & Triceps','Back & Biceps','Legs','Shoulders & Core','Rest Day','Full Body'],
        ];
        $focus_cycle = $focuses[$goal] ?? $focuses['maintain'];
    }

    for ($d = 1; $d <= 30; $d++) {
        $focus_idx = ($d - 1) % count($focus_cycle);
        $focus     = $focus_cycle[$focus_idx];
        $is_rest   = ($focus === 'Rest Day');

        if ($is_rest) {
            $schedule[$d] = ['day' => $d, 'focus' => 'Rest Day', 'is_rest' => true, 'exercises' => []];
        } else {
            // Pick 5 exercises from pool (rotate)
            $offset = ($workout_day * 3) % count($all);
            $picks  = array_slice($all, $offset, 5);
            if (count($picks) < 5) $picks = array_merge($picks, array_slice($all, 0, 5 - count($picks)));

            $exs = [];
            foreach ($picks as $name) {
                $exs[] = array_merge(['name' => $name], $pool[$name]);
            }
            $schedule[$d] = ['day' => $d, 'focus' => $focus, 'is_rest' => false, 'exercises' => $exs];
            $workout_day++;
        }
    }
    return $schedule;
}