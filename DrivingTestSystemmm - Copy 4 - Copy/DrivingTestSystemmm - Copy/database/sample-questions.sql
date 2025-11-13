-- Sample questions for mock tests

USE driving_test_system;

-- Road signs and markings
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('What does a red octagonal sign mean?', 'multiple_choice', 'Yield', 'Stop', 'No Entry', 'Speed Limit', 'B', 'A red octagonal (8-sided) sign always means STOP. You must come to a complete stop.', 'Road Signs', 'easy'),
('What should you do when you see a yellow traffic light?', 'multiple_choice', 'Speed up to get through', 'Stop if safe to do so', 'Continue at same speed', 'Honk your horn', 'B', 'A yellow light means the signal is about to turn red. You should stop if you can do so safely.', 'Traffic Signals', 'easy'),
('What does a triangular sign with a red border mean?', 'multiple_choice', 'Information', 'Warning', 'Prohibition', 'Mandatory', 'B', 'Triangular signs with red borders are warning signs that alert you to hazards ahead.', 'Road Signs', 'medium'),
('A solid white line on your side of the road means:', 'multiple_choice', 'You may overtake', 'No overtaking allowed', 'Parking allowed', 'Bus lane', 'B', 'A solid white line means you must not cross it to overtake.', 'Road Markings', 'medium'),
('What is the speed limit in a school zone during school hours?', 'multiple_choice', '30 km/h', '40 km/h', '50 km/h', '60 km/h', 'A', 'School zones typically have a 30 km/h speed limit during school hours for safety.', 'Speed Limits', 'easy');

-- Right of way
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('At a 4-way stop, who has the right of way?', 'multiple_choice', 'Largest vehicle', 'Vehicle that arrived first', 'Vehicle on the right', 'Vehicle going straight', 'B', 'At a 4-way stop, the vehicle that arrives first has the right of way. If arriving simultaneously, yield to the right.', 'Right of Way', 'medium'),
('When entering a roundabout, you must:', 'multiple_choice', 'Speed up', 'Yield to traffic already in the roundabout', 'Honk your horn', 'Stop completely', 'B', 'You must always yield to traffic already circulating in the roundabout.', 'Right of Way', 'medium'),
('A pedestrian is crossing at a marked crosswalk. You must:', 'multiple_choice', 'Honk to warn them', 'Stop and let them cross', 'Slow down slightly', 'Flash your lights', 'B', 'You must always stop and yield to pedestrians at marked crosswalks.', 'Right of Way', 'easy'),
('When two vehicles approach an uncontrolled intersection at the same time, who yields?', 'multiple_choice', 'Both stop', 'Vehicle on the left', 'Vehicle on the right', 'Faster vehicle', 'B', 'The vehicle on the left must yield to the vehicle on the right.', 'Right of Way', 'medium');

-- Safe driving practices
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('The safest following distance is:', 'multiple_choice', '1 second', '2 seconds', '3 seconds', '5 seconds', 'C', 'A 3-second following distance gives you adequate time to react to sudden stops.', 'Safe Driving', 'easy'),
('Before changing lanes, you should:', 'multiple_choice', 'Signal only', 'Check mirrors and blind spot', 'Speed up', 'Slow down', 'B', 'Always check mirrors and blind spots before changing lanes as vehicles may be in areas you cannot see.', 'Safe Driving', 'easy'),
('In wet weather, you should:', 'multiple_choice', 'Drive faster', 'Increase following distance', 'Use cruise control', 'Brake harder', 'B', 'Wet roads reduce traction, so increase following distance to allow more stopping time.', 'Safe Driving', 'medium'),
('Using a mobile phone while driving is:', 'multiple_choice', 'Allowed with hands-free', 'Never allowed', 'Allowed at traffic lights', 'Allowed in emergencies only', 'A', 'Using hands-free devices is typically allowed, but handheld use is prohibited.', 'Safe Driving', 'medium'),
('You should check your mirrors:', 'multiple_choice', 'Only when changing lanes', 'Every 5-8 seconds', 'Only when reversing', 'Once per trip', 'B', 'Regular mirror checks every 5-8 seconds help maintain awareness of traffic around you.', 'Safe Driving', 'easy');

-- Vehicle control
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('If your vehicle starts to skid, you should:', 'multiple_choice', 'Brake hard', 'Steer in the direction of the skid', 'Accelerate', 'Turn the wheel sharply', 'B', 'Steering in the direction of the skid helps regain control of the vehicle.', 'Vehicle Control', 'hard'),
('When parking on a hill facing upward, you should:', 'multiple_choice', 'Turn wheels toward curb', 'Turn wheels away from curb', 'Leave wheels straight', 'Turn on hazards', 'B', 'Turn wheels away from the curb so if the car rolls, it will go into the curb.', 'Vehicle Control', 'medium'),
('You should use high beam headlights:', 'multiple_choice', 'In the city', 'On well-lit roads', 'On dark rural roads', 'During rain', 'C', 'High beams are for dark rural roads where there is no oncoming traffic.', 'Vehicle Control', 'easy'),
('The purpose of ABS brakes is to:', 'multiple_choice', 'Stop faster', 'Prevent wheel lockup', 'Reduce brake wear', 'Save fuel', 'B', 'ABS prevents wheels from locking up during hard braking, maintaining steering control.', 'Vehicle Control', 'medium');

-- Laws and regulations
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('The legal blood alcohol limit for drivers is:', 'multiple_choice', '0.05%', '0.08%', '0.10%', '0.00%', 'B', 'The legal BAC limit is typically 0.08% for regular drivers (may vary by jurisdiction).', 'Laws', 'easy'),
('Seat belts must be worn by:', 'multiple_choice', 'Driver only', 'Front passengers only', 'All occupants', 'Adults only', 'C', 'All vehicle occupants must wear seat belts regardless of seating position.', 'Laws', 'easy'),
('You must report an accident to police if:', 'multiple_choice', 'Any damage occurs', 'Damage exceeds $1000', 'Anyone is injured', 'Your car is damaged', 'C', 'You must report accidents involving injuries, deaths, or significant property damage.', 'Laws', 'medium'),
('A driver license suspension can result from:', 'multiple_choice', 'Too many parking tickets', 'DUI conviction', 'Expired registration', 'Minor speeding', 'B', 'DUI convictions typically result in license suspension along with other penalties.', 'Laws', 'easy');

-- True/False questions
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('You can turn right on a red light after stopping and checking for traffic.', 'true_false', 'True', 'False', NULL, NULL, 'A', 'In most jurisdictions, you can turn right on red after a complete stop if safe and no sign prohibits it.', 'Traffic Rules', 'easy'),
('It is legal to exceed the speed limit when passing another vehicle.', 'true_false', 'True', 'False', NULL, NULL, 'B', 'You must never exceed the speed limit, even when passing other vehicles.', 'Speed Limits', 'easy'),
('You should always use turn signals when changing lanes or turning.', 'true_false', 'True', 'False', NULL, NULL, 'A', 'Turn signals must always be used to communicate your intentions to other drivers.', 'Safe Driving', 'easy'),
('It is safe to drive with worn tires as long as you drive slowly.', 'true_false', 'True', 'False', NULL, NULL, 'B', 'Worn tires are dangerous at any speed as they reduce traction and increase stopping distance.', 'Vehicle Safety', 'easy'),
('You must yield to emergency vehicles with lights and sirens.', 'true_false', 'True', 'False', NULL, NULL, 'A', 'You must always pull over and yield to emergency vehicles with activated lights and sirens.', 'Right of Way', 'easy'),
('Hands-free phone use is always safe while driving.', 'true_false', 'True', 'False', NULL, NULL, 'B', 'Even hands-free phone use can be distracting and impair your driving ability.', 'Safe Driving', 'medium'),
('You should increase following distance in bad weather.', 'true_false', 'True', 'False', NULL, NULL, 'A', 'Bad weather reduces visibility and traction, requiring more following distance for safety.', 'Safe Driving', 'easy'),
('Parking in a disabled spot without a permit carries no penalty.', 'true_false', 'True', 'False', NULL, NULL, 'B', 'Parking in disabled spots without proper authorization results in fines and penalties.', 'Laws', 'easy');

-- Additional multiple choice questions
INSERT INTO questions (question_text, question_type, option_a, option_b, option_c, option_d, correct_answer, explanation, category, difficulty) VALUES
('When approaching a school bus with flashing red lights, you must:', 'multiple_choice', 'Slow down', 'Stop at least 20 meters away', 'Change lanes', 'Proceed with caution', 'B', 'When a school bus has flashing red lights, all traffic must stop at a safe distance.', 'Traffic Rules', 'easy'),
('Hydroplaning occurs when:', 'multiple_choice', 'Tires are overinflated', 'Tires lose contact with road due to water', 'Driving too slowly', 'Brakes fail', 'B', 'Hydroplaning happens when water builds up between tires and road surface, causing loss of traction.', 'Safe Driving', 'hard'),
('The best way to handle road rage is to:', 'multiple_choice', 'Confront the driver', 'Stay calm and avoid engagement', 'Speed up', 'Block the road', 'B', 'The safest response to aggressive drivers is to remain calm and not engage.', 'Safe Driving', 'medium'),
('When parallel parking, your vehicle should be:', 'multiple_choice', '10 cm from curb', '15-30 cm from curb', '50 cm from curb', 'Touching the curb', 'B', 'When parallel parking, your vehicle should be 15-30 cm from the curb.', 'Vehicle Control', 'easy'),
('What does a flashing yellow light mean?', 'multiple_choice', 'Stop', 'Speed up', 'Proceed with caution', 'Yield', 'C', 'A flashing yellow light means proceed with caution and be prepared to yield.', 'Traffic Signals', 'easy'),
('You should dim your high beams when:', 'multiple_choice', 'In the city only', 'Within 150m of oncoming traffic', 'Never', 'Only at night', 'B', 'Dim high beams within 150 meters of oncoming traffic or when following another vehicle closely.', 'Vehicle Control', 'medium'),
('A broken white line on the road means:', 'multiple_choice', 'No passing', 'Passing allowed when safe', 'Construction zone', 'Bike lane', 'B', 'A broken white line indicates passing is allowed when safe to do so.', 'Road Markings', 'easy'),
('The purpose of a rumble strip is to:', 'multiple_choice', 'Slow down traffic', 'Alert drowsy drivers', 'Mark lane boundaries', 'Improve traction', 'B', 'Rumble strips create noise and vibration to alert drivers who drift off the road.', 'Road Safety', 'medium');
