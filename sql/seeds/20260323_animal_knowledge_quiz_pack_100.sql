USE cloud_signage_present;

START TRANSACTION;

SET @owner_admin_id := 1;
SET @countdown_seconds := 12;
SET @reveal_duration := 6;
SET @created_at := UTC_TIMESTAMP();

CREATE TEMPORARY TABLE tmp_animal_knowledge_questions_100 (
    sort_order INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL
);

INSERT INTO tmp_animal_knowledge_questions_100
    (sort_order, question_text, option_a, option_b, option_c, option_d, correct_option)
VALUES
    (1, 'What is the largest living land animal?', 'African elephant', 'White rhinoceros', 'Hippopotamus', 'Giraffe', 'A'),
    (2, 'Which mammal is capable of true powered flight?', 'Flying squirrel', 'Bat', 'Sugar glider', 'Colugo', 'B'),
    (3, 'What is a group of lions called?', 'School', 'Pack', 'Pride', 'Herd', 'C'),
    (4, 'Which bird is known for its ability to mimic human speech?', 'Parrot', 'Sparrow', 'Penguin', 'Pelican', 'A'),
    (5, 'What is the fastest land animal?', 'Lion', 'Cheetah', 'Gazelle', 'Greyhound', 'B'),
    (6, 'Which animal is the tallest living land animal?', 'Camel', 'Ostrich', 'Elephant', 'Giraffe', 'D'),
    (7, 'What is a baby kangaroo called?', 'Joey', 'Cub', 'Calf', 'Pup', 'A'),
    (8, 'Which marine mammal is famous for its long spiral tusk?', 'Walrus', 'Beluga', 'Narwhal', 'Seal', 'C'),
    (9, 'What is the largest species of penguin?', 'King penguin', 'Emperor penguin', 'Adelie penguin', 'Gentoo penguin', 'B'),
    (10, 'Which animal is known for changing color to blend with its surroundings?', 'Iguana', 'Chameleon', 'Gecko', 'Salamander', 'B'),
    (11, 'What is a group of wolves commonly called?', 'Pack', 'Pride', 'Pod', 'Colony', 'A'),
    (12, 'Which animal has black-and-white stripes?', 'Okapi', 'Hyena', 'Zebra', 'Tapir', 'C'),
    (13, 'Which is the largest species of shark?', 'Great white shark', 'Whale shark', 'Tiger shark', 'Hammerhead shark', 'B'),
    (14, 'What type of animal is a Komodo dragon?', 'Amphibian', 'Bird', 'Fish', 'Lizard', 'D'),
    (15, 'Which animal is known as the king of the jungle in popular culture?', 'Tiger', 'Lion', 'Bear', 'Leopard', 'B'),
    (16, 'What is a baby goat called?', 'Foal', 'Kid', 'Calf', 'Lamb', 'B'),
    (17, 'Which mammal lays eggs?', 'Koala', 'Platypus', 'Kangaroo', 'Dolphin', 'B'),
    (18, 'What is the largest cat species in the wild?', 'Jaguar', 'Leopard', 'Tiger', 'Cheetah', 'C'),
    (19, 'Which animal is famous for building dams?', 'Otter', 'Beaver', 'Badger', 'Muskrat', 'B'),
    (20, 'How many legs does an adult insect have?', 'Six', 'Eight', 'Ten', 'Four', 'A'),
    (21, 'What is a group of dolphins called?', 'Troop', 'Pride', 'Pod', 'Flock', 'C'),
    (22, 'Which bird cannot fly and is native to New Zealand?', 'Kiwi', 'Puffin', 'Heron', 'Falcon', 'A'),
    (23, 'What is the only continent where wild penguins are not native?', 'Africa', 'South America', 'Asia', 'Antarctica', 'C'),
    (24, 'Which animal has the longest neck?', 'Camel', 'Giraffe', 'Llama', 'Moose', 'B'),
    (25, 'What is a baby swan called?', 'Chick', 'Cygnet', 'Poult', 'Fawn', 'B'),
    (26, 'Which sea creature has eight arms?', 'Squid', 'Starfish', 'Octopus', 'Crab', 'C'),
    (27, 'Which mammal is known for having a pouch?', 'Otter', 'Wombat', 'Marsupial', 'Seal', 'C'),
    (28, 'What is the slow-moving mammal that spends much of its life hanging upside down?', 'Lemur', 'Sloth', 'Koala', 'Pangolin', 'B'),
    (29, 'Which bird is a universal symbol of peace?', 'Dove', 'Eagle', 'Crow', 'Swan', 'A'),
    (30, 'What is a group of fish called?', 'Herd', 'School', 'Pack', 'Brood', 'B'),
    (31, 'Which animal is covered with protective quills?', 'Otter', 'Porcupine', 'Mole', 'Ferret', 'B'),
    (32, 'Which large marine mammal is famous for complex songs?', 'Manatee', 'Blue whale', 'Walrus', 'Sea lion', 'B'),
    (33, 'What is the largest living species of turtle?', 'Leatherback sea turtle', 'Green sea turtle', 'Loggerhead sea turtle', 'Aldabra giant tortoise', 'A'),
    (34, 'Which bird is known for delivering babies in folklore?', 'Stork', 'Heron', 'Crane', 'Pelican', 'A'),
    (35, 'What type of animal is an axolotl?', 'Reptile', 'Fish', 'Amphibian', 'Bird', 'C'),
    (36, 'Which animal is known to have a trunk?', 'Tapir', 'Elephant', 'Anteater', 'Boar', 'B'),
    (37, 'What is a baby deer called?', 'Foal', 'Fawn', 'Cub', 'Kid', 'B'),
    (38, 'Which animal can curl into a tight ball for defense and has scales made of keratin?', 'Armadillo', 'Pangolin', 'Hedgehog', 'Echidna', 'B'),
    (39, 'Which bird is famous for being unable to fly but able to run very fast?', 'Emu', 'Duck', 'Partridge', 'Pigeon', 'A'),
    (40, 'What is a male chicken called?', 'Hen', 'Rooster', 'Gander', 'Drake', 'B'),
    (41, 'Which animal is the largest living primate?', 'Orangutan', 'Chimpanzee', 'Gorilla', 'Baboon', 'C'),
    (42, 'Which marine animal is known for having five arms in its common form?', 'Jellyfish', 'Starfish', 'Sea cucumber', 'Urchin', 'B'),
    (43, 'What is the name for a group of bees living together?', 'Hive', 'Nest', 'Den', 'Burrow', 'A'),
    (44, 'Which mammal is famous for spines and can also be called a hedgehog''s larger relative in some regions?', 'Porcupine', 'Otter', 'Weasel', 'Raccoon', 'A'),
    (45, 'What is a baby horse called?', 'Foal', 'Colt', 'Calf', 'Pup', 'A'),
    (46, 'Which animal is known for playing dead as a defense behavior?', 'Opossum', 'Raccoon', 'Skunk', 'Mink', 'A'),
    (47, 'Which bird of prey is associated with excellent eyesight?', 'Eagle', 'Pheasant', 'Robin', 'Wren', 'A'),
    (48, 'What is the common name for a poisonous amphibian with bright warning colors and smooth skin?', 'Tree frog', 'Poison dart frog', 'Toad', 'Newt', 'B'),
    (49, 'Which big cat is known for its roar and mane on adult males?', 'Jaguar', 'Cheetah', 'Lion', 'Puma', 'C'),
    (50, 'What is the largest species of bear?', 'Brown bear', 'Polar bear', 'Black bear', 'Sun bear', 'B'),
    (51, 'Which animal is known for its black-and-white face markings and bamboo diet?', 'Badger', 'Panda', 'Raccoon', 'Koala', 'B'),
    (52, 'What is a baby sheep called?', 'Kid', 'Foal', 'Lamb', 'Calf', 'C'),
    (53, 'Which reptile is known for a hard shell and slow movement on land?', 'Salamander', 'Tortoise', 'Iguana', 'Gecko', 'B'),
    (54, 'Which animal is famous for squirting ink as a defense?', 'Jellyfish', 'Octopus', 'Lobster', 'Seal', 'B'),
    (55, 'Which mammal has a black-and-white coat and is native to China?', 'Zebra', 'Panda', 'Tapir', 'Lemur', 'B'),
    (56, 'What is the largest species of deer?', 'Moose', 'Elk', 'Reindeer', 'Red deer', 'A'),
    (57, 'Which animal has a distinctive hammer-shaped head?', 'Sawfish', 'Hammerhead shark', 'Swordfish', 'Manta ray', 'B'),
    (58, 'What is a group of owls called?', 'Parliament', 'Pod', 'Brood', 'Cloud', 'A'),
    (59, 'Which desert animal stores fat in its humps rather than water?', 'Camel', 'Llama', 'Alpaca', 'Yak', 'A'),
    (60, 'Which animal is known for its armored shell and habit of rolling up in some species?', 'Pangolin', 'Armadillo', 'Porcupine', 'Meerkat', 'B'),
    (61, 'Which bird is famous for colorful tail feathers displayed by the male?', 'Peacock', 'Flamingo', 'Toucan', 'Macaw', 'A'),
    (62, 'What is a baby rabbit called?', 'Kit', 'Cub', 'Pup', 'Fawn', 'A'),
    (63, 'Which marine mammal has long tusks and whiskers?', 'Narwhal', 'Seal', 'Walrus', 'Otter', 'C'),
    (64, 'Which animal is known for standing on one leg and pink feathers?', 'Flamingo', 'Heron', 'Pelican', 'Spoonbill', 'A'),
    (65, 'What is the largest living bird by height and weight?', 'Emu', 'Ostrich', 'Condor', 'Albatross', 'B'),
    (66, 'Which animal uses echolocation and lives in the sea?', 'Seal', 'Dolphin', 'Walrus', 'Manatee', 'B'),
    (67, 'Which insect is known for making honey?', 'Wasp', 'Bee', 'Ant', 'Beetle', 'B'),
    (68, 'What is a group of crows commonly called?', 'Murder', 'Parliament', 'Charm', 'Husk', 'A'),
    (69, 'Which mammal is famous for very slow movement and long claws for hanging?', 'Anteater', 'Sloth', 'Koala', 'Loris', 'B'),
    (70, 'Which reptile is known for dropping its tail to escape predators?', 'Turtle', 'Gecko', 'Crocodile', 'Python', 'B'),
    (71, 'What is the only mammal naturally covered in scales?', 'Armadillo', 'Pangolin', 'Hedgehog', 'Platypus', 'B'),
    (72, 'Which bird has the largest wingspan among living birds?', 'Golden eagle', 'Wandering albatross', 'Condor', 'Pelican', 'B'),
    (73, 'Which animal is known for laughing calls and living in clans in Africa?', 'Jackal', 'Hyena', 'Meerkat', 'Baboon', 'B'),
    (74, 'What is a baby fox called?', 'Pup', 'Kit', 'Cub', 'Joey', 'B'),
    (75, 'Which animal has a prehensile tail and is native to Madagascar?', 'Lemur', 'Capybara', 'Otter', 'Ibex', 'A'),
    (76, 'Which rodent is the largest in the world?', 'Beaver', 'Capybara', 'Marmot', 'Porcupine', 'B'),
    (77, 'Which big cat is recognized for black rosettes on a golden coat and strong climbing ability?', 'Lion', 'Jaguar', 'Leopard', 'Cheetah', 'C'),
    (78, 'What is the name for a group of giraffes?', 'Tower', 'Pack', 'Raft', 'Pod', 'A'),
    (79, 'Which animal is known for its broad bill and ability to sense electric fields underwater?', 'Otter', 'Platypus', 'Muskrat', 'Mole', 'B'),
    (80, 'Which amphibian is often mistaken for a reptile and can regenerate lost limbs?', 'Salamander', 'Turtle', 'Skink', 'Iguana', 'A'),
    (81, 'What is a baby pig called?', 'Piglet', 'Pup', 'Calf', 'Chick', 'A'),
    (82, 'Which primate is known for orange hair and living in Borneo and Sumatra?', 'Gorilla', 'Baboon', 'Orangutan', 'Gibbon', 'C'),
    (83, 'Which animal is the tallest flightless bird after the ostrich?', 'Cassowary', 'Emu', 'Kiwi', 'Penguin', 'B'),
    (84, 'Which sea animal has a shell and is famous for producing pearls in some species?', 'Oyster', 'Starfish', 'Sea urchin', 'Shrimp', 'A'),
    (85, 'What is a group of geese called when on the ground?', 'Gaggle', 'Pod', 'Clowder', 'Drift', 'A'),
    (86, 'Which animal is known for carrying its young in a pouch and hopping?', 'Wallaby', 'Kangaroo', 'Koala', 'Possum', 'B'),
    (87, 'Which bird is associated with wisdom in folklore?', 'Owl', 'Robin', 'Crow', 'Stork', 'A'),
    (88, 'What is the largest living reptile?', 'Saltwater crocodile', 'Komodo dragon', 'Green anaconda', 'Leatherback turtle', 'A'),
    (89, 'Which animal is known for its ringed tail and mask-like face?', 'Raccoon', 'Coati', 'Badger', 'Otter', 'A'),
    (90, 'Which mammal spends most of its life in water and is the largest animal on Earth?', 'Blue whale', 'Sperm whale', 'Orca', 'Elephant seal', 'A'),
    (91, 'What is a baby duck called?', 'Duckling', 'Gosling', 'Chick', 'Poult', 'A'),
    (92, 'Which animal is known for building large underground colonies and mounds in some species?', 'Termite', 'Butterfly', 'Dragonfly', 'Moth', 'A'),
    (93, 'Which cat-sized mammal from Australia is famous for sleeping long hours in eucalyptus trees?', 'Wombat', 'Koala', 'Quokka', 'Tasmanian devil', 'B'),
    (94, 'Which animal is famous for its black-and-white coat and tusk-like canine teeth in males?', 'Seal', 'Walrus', 'Sea lion', 'Dugong', 'B'),
    (95, 'Which snake is famous for squeezing prey rather than using venom?', 'Cobra', 'Viper', 'Boa constrictor', 'Krait', 'C'),
    (96, 'What is the name for a group of cats?', 'Clowder', 'Murder', 'Parliament', 'Drove', 'A'),
    (97, 'Which animal is the largest member of the dog family?', 'Gray wolf', 'African wild dog', 'Hyena', 'Coyote', 'A'),
    (98, 'Which bird is known for hovering and feeding on nectar?', 'Kingfisher', 'Hummingbird', 'Canary', 'Finch', 'B'),
    (99, 'Which animal has a long sticky tongue and is specialized for eating ants and termites?', 'Aardvark', 'Anteater', 'Armadillo', 'Meerkat', 'B'),
    (100, 'What is a baby seal commonly called?', 'Pup', 'Kit', 'Calf', 'Cub', 'A');

INSERT INTO quiz_questions
    (owner_admin_id, question_text, option_a, option_b, option_c, option_d, correct_option, countdown_seconds, reveal_duration, active, created_at, updated_at)
SELECT
    @owner_admin_id,
    t.question_text,
    t.option_a,
    t.option_b,
    t.option_c,
    t.option_d,
    t.correct_option,
    @countdown_seconds,
    @reveal_duration,
    1,
    @created_at,
    @created_at
FROM tmp_animal_knowledge_questions_100 t
WHERE NOT EXISTS (
    SELECT 1
    FROM quiz_questions q
    WHERE q.owner_admin_id = @owner_admin_id
      AND q.question_text = t.question_text
);

DROP TEMPORARY TABLE tmp_animal_knowledge_questions_100;

COMMIT;
