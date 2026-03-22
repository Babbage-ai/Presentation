USE cloud_signage_present;

START TRANSACTION;

SET @owner_admin_id := 1;
SET @countdown_seconds := 12;
SET @reveal_duration := 6;
SET @created_at := UTC_TIMESTAMP();

CREATE TEMPORARY TABLE tmp_general_knowledge_questions_100 (
    sort_order INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL
);

INSERT INTO tmp_general_knowledge_questions_100
    (sort_order, question_text, option_a, option_b, option_c, option_d, correct_option)
VALUES
    (1, 'What is the capital of New Zealand?', 'Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'B'),
    (2, 'Which element has the chemical symbol Na?', 'Nitrogen', 'Neon', 'Sodium', 'Nickel', 'C'),
    (3, 'Who wrote 1984?', 'George Orwell', 'Aldous Huxley', 'Ernest Hemingway', 'Ray Bradbury', 'A'),
    (4, 'How many degrees are in a right angle?', '45', '90', '120', '180', 'B'),
    (5, 'Which country is famous for the city of Machu Picchu?', 'Chile', 'Bolivia', 'Peru', 'Ecuador', 'C'),
    (6, 'What is the largest internal organ in the human body?', 'Heart', 'Liver', 'Lung', 'Kidney', 'B'),
    (7, 'Which planet is known for its Great Red Spot?', 'Mars', 'Saturn', 'Jupiter', 'Neptune', 'C'),
    (8, 'Who painted The Starry Night?', 'Claude Monet', 'Vincent van Gogh', 'Salvador Dali', 'Edgar Degas', 'B'),
    (9, 'What is the square root of 144?', '10', '11', '12', '13', 'C'),
    (10, 'Which country uses the yen as its currency?', 'China', 'Japan', 'South Korea', 'Vietnam', 'B'),
    (11, 'What is the process by which plants make food using sunlight?', 'Respiration', 'Fermentation', 'Photosynthesis', 'Transpiration', 'C'),
    (12, 'Which sea separates Europe and Africa?', 'Black Sea', 'Mediterranean Sea', 'Baltic Sea', 'Red Sea', 'B'),
    (13, 'Who developed the laws of motion and universal gravitation?', 'Galileo Galilei', 'Johannes Kepler', 'Isaac Newton', 'Niels Bohr', 'C'),
    (14, 'Which instrument is used to measure temperature?', 'Barometer', 'Thermometer', 'Altimeter', 'Compass', 'B'),
    (15, 'What is the largest species of shark?', 'Great white shark', 'Hammerhead shark', 'Whale shark', 'Tiger shark', 'C'),
    (16, 'Which country has the city of Marrakech?', 'Morocco', 'Tunisia', 'Turkey', 'Greece', 'A'),
    (17, 'What is the name of the fairy in Peter Pan?', 'Nimue', 'Tinker Bell', 'Wendy', 'Elsa', 'B'),
    (18, 'How many minutes are in three hours?', '120', '150', '180', '210', 'C'),
    (19, 'Which continent is Argentina located in?', 'Europe', 'South America', 'Africa', 'Asia', 'B'),
    (20, 'Which gas is most abundant in Earth''s atmosphere?', 'Oxygen', 'Carbon dioxide', 'Nitrogen', 'Argon', 'C'),
    (21, 'Who was the first female Prime Minister of the United Kingdom?', 'Theresa May', 'Margaret Thatcher', 'Angela Merkel', 'Indira Gandhi', 'B'),
    (22, 'Which animal is known for carrying its home on its back?', 'Armadillo', 'Turtle', 'Hedgehog', 'Otter', 'B'),
    (23, 'What is the capital of Norway?', 'Oslo', 'Bergen', 'Stockholm', 'Helsinki', 'A'),
    (24, 'How many strings does a standard violin have?', 'Four', 'Five', 'Six', 'Seven', 'A'),
    (25, 'Which metal is used in electrical wiring because of its conductivity?', 'Tin', 'Copper', 'Lead', 'Zinc', 'B'),
    (26, 'What is the largest island in the world?', 'Borneo', 'Madagascar', 'Greenland', 'New Guinea', 'C'),
    (27, 'Which writer created Sherlock Holmes?', 'Agatha Christie', 'Arthur Conan Doyle', 'Edgar Allan Poe', 'Jules Verne', 'B'),
    (28, 'What is the Roman numeral for 50?', 'L', 'C', 'D', 'X', 'A'),
    (29, 'Which country is home to the city of Dubrovnik?', 'Croatia', 'Slovenia', 'Serbia', 'Romania', 'A'),
    (30, 'Which part of the cell contains genetic material?', 'Nucleus', 'Membrane', 'Cytoplasm', 'Ribosome', 'A'),
    (31, 'Who sang the song Imagine?', 'Paul McCartney', 'Elton John', 'John Lennon', 'David Bowie', 'C'),
    (32, 'Which planet is tilted so much that it rotates on its side?', 'Mars', 'Uranus', 'Venus', 'Mercury', 'B'),
    (33, 'What is the capital city of Kenya?', 'Nairobi', 'Mombasa', 'Kampala', 'Addis Ababa', 'A'),
    (34, 'Which language is primarily spoken in Argentina?', 'Portuguese', 'Spanish', 'Italian', 'French', 'B'),
    (35, 'What do you call a word that is the opposite in meaning to another word?', 'Synonym', 'Homonym', 'Antonym', 'Acronym', 'C'),
    (36, 'Which ocean is between Africa and Australia?', 'Atlantic Ocean', 'Indian Ocean', 'Pacific Ocean', 'Arctic Ocean', 'B'),
    (37, 'Who composed the opera The Magic Flute?', 'Mozart', 'Verdi', 'Chopin', 'Handel', 'A'),
    (38, 'Which city is the capital of the Netherlands?', 'Rotterdam', 'The Hague', 'Amsterdam', 'Utrecht', 'C'),
    (39, 'How many bones are in the adult human body?', '206', '201', '212', '198', 'A'),
    (40, 'Which country is famous for inventing pizza in its modern form?', 'France', 'Italy', 'Greece', 'Spain', 'B'),
    (41, 'What is the brightest star in the night sky?', 'Polaris', 'Sirius', 'Betelgeuse', 'Rigel', 'B'),
    (42, 'Which scientist is associated with the discovery of radioactivity alongside Pierre Curie?', 'Rosalind Franklin', 'Marie Curie', 'Jane Goodall', 'Dorothy Hodgkin', 'B'),
    (43, 'What is the capital of Portugal?', 'Porto', 'Lisbon', 'Madrid', 'Braga', 'B'),
    (44, 'Which organ is used for hearing?', 'Nose', 'Ear', 'Eye', 'Tongue', 'B'),
    (45, 'Which country has the maple syrup industry most associated with it?', 'Canada', 'United States', 'Sweden', 'Austria', 'A'),
    (46, 'What is 15 multiplied by 6?', '80', '85', '90', '95', 'C'),
    (47, 'Which river runs through Egypt?', 'Jordan River', 'Nile', 'Tigris', 'Danube', 'B'),
    (48, 'Who is the Greek god of the sea?', 'Apollo', 'Hermes', 'Poseidon', 'Ares', 'C'),
    (49, 'Which country is known as the Land of the Rising Sun?', 'Japan', 'China', 'Thailand', 'Philippines', 'A'),
    (50, 'What is the main ingredient in traditional hummus?', 'Lentils', 'Chickpeas', 'Black beans', 'Peanuts', 'B'),
    (51, 'Which continent contains the Amazon rainforest?', 'Africa', 'Asia', 'South America', 'North America', 'C'),
    (52, 'How many months have 31 days?', 'Five', 'Six', 'Seven', 'Eight', 'C'),
    (53, 'Which planet is often called Earth''s twin because of similar size?', 'Mars', 'Venus', 'Mercury', 'Saturn', 'B'),
    (54, 'What is the hardest tissue in the human body?', 'Bone', 'Cartilage', 'Enamel', 'Muscle', 'C'),
    (55, 'Who wrote Pride and Prejudice?', 'Emily Bronte', 'Jane Austen', 'Charlotte Bronte', 'Mary Shelley', 'B'),
    (56, 'What is the capital of South Korea?', 'Seoul', 'Busan', 'Incheon', 'Daegu', 'A'),
    (57, 'Which bird is the national symbol of the United States?', 'Bald eagle', 'Peregrine falcon', 'Owl', 'Condor', 'A'),
    (58, 'Which instrument keeps time in an orchestra?', 'Tuba', 'Triangle', 'Metronome', 'Trombone', 'C'),
    (59, 'What is the nearest star to Earth besides the Sun?', 'Sirius', 'Alpha Centauri', 'Proxima Centauri', 'Vega', 'C'),
    (60, 'Which city hosted the 2012 Summer Olympics?', 'Beijing', 'Rio de Janeiro', 'London', 'Athens', 'C'),
    (61, 'What is the value of pi rounded to two decimal places?', '3.12', '3.14', '3.16', '3.18', 'B'),
    (62, 'Which country has the city of Casablanca?', 'Morocco', 'Algeria', 'Egypt', 'Lebanon', 'A'),
    (63, 'What is the primary gas found in the bubbles of soft drinks?', 'Oxygen', 'Carbon dioxide', 'Nitrogen', 'Hydrogen', 'B'),
    (64, 'Who painted Girl with a Pearl Earring?', 'Johannes Vermeer', 'Rembrandt', 'Rubens', 'Matisse', 'A'),
    (65, 'Which country is home to Mount Fuji?', 'China', 'Japan', 'Nepal', 'Indonesia', 'B'),
    (66, 'What is the smallest continent by land area?', 'Europe', 'Antarctica', 'Australia', 'South America', 'C'),
    (67, 'How many centimeters are in a meter?', '10', '100', '1,000', '12', 'B'),
    (68, 'Which famous ship sank in 1912 after hitting an iceberg?', 'Lusitania', 'Britannic', 'Titanic', 'Bismarck', 'C'),
    (69, 'What is the capital of Switzerland?', 'Geneva', 'Zurich', 'Bern', 'Basel', 'C'),
    (70, 'Which branch of mathematics deals with shapes and angles?', 'Algebra', 'Geometry', 'Calculus', 'Statistics', 'B'),
    (71, 'Which country gifted cherry trees to Washington, D.C.?', 'China', 'Japan', 'South Korea', 'Canada', 'B'),
    (72, 'What is the name of the longest bone in the human body?', 'Tibia', 'Humerus', 'Femur', 'Fibula', 'C'),
    (73, 'Which gas do humans need to breathe to survive?', 'Hydrogen', 'Carbon dioxide', 'Oxygen', 'Helium', 'C'),
    (74, 'Who wrote The Odyssey?', 'Homer', 'Virgil', 'Socrates', 'Aristotle', 'A'),
    (75, 'Which country is famous for the landmark Petra?', 'Jordan', 'Egypt', 'Israel', 'Saudi Arabia', 'A'),
    (76, 'What is 9 squared?', '72', '81', '99', '108', 'B'),
    (77, 'Which ocean is west of California?', 'Atlantic Ocean', 'Indian Ocean', 'Pacific Ocean', 'Southern Ocean', 'C'),
    (78, 'Which artist is known for the sculpture David?', 'Michelangelo', 'Donatello', 'Bernini', 'Rodin', 'A'),
    (79, 'What is the capital of Austria?', 'Salzburg', 'Vienna', 'Graz', 'Prague', 'B'),
    (80, 'Which language has the most native speakers in the world?', 'English', 'Spanish', 'Mandarin Chinese', 'Hindi', 'C'),
    (81, 'What is the study of earthquakes called?', 'Meteorology', 'Seismology', 'Ecology', 'Volcanology', 'B'),
    (82, 'Which country is known for the ancient city of Athens?', 'Italy', 'Turkey', 'Greece', 'Cyprus', 'C'),
    (83, 'How many planets are in the solar system?', 'Seven', 'Eight', 'Nine', 'Ten', 'B'),
    (84, 'Which is the largest cat species in the wild?', 'Lion', 'Leopard', 'Tiger', 'Jaguar', 'C'),
    (85, 'What is the capital of Ireland?', 'Cork', 'Dublin', 'Belfast', 'Galway', 'B'),
    (86, 'Who developed the theory of evolution by natural selection?', 'Gregor Mendel', 'Charles Darwin', 'Louis Pasteur', 'Carl Linnaeus', 'B'),
    (87, 'What is the currency of India?', 'Rupee', 'Peso', 'Rand', 'Rial', 'A'),
    (88, 'Which famous wall once divided East and West Berlin?', 'Great Wall', 'Iron Curtain', 'Berlin Wall', 'Hadrian''s Wall', 'C'),
    (89, 'What is the largest moon of Saturn?', 'Europa', 'Titan', 'Io', 'Ganymede', 'B'),
    (90, 'Which country has Stockholm as its capital?', 'Sweden', 'Norway', 'Denmark', 'Finland', 'A'),
    (91, 'Which part of a plant absorbs water from the soil?', 'Stem', 'Leaf', 'Root', 'Petal', 'C'),
    (92, 'What is the name of the first month of the year?', 'January', 'February', 'March', 'December', 'A'),
    (93, 'Which country is known for the city of Cape Town?', 'South Africa', 'Kenya', 'Namibia', 'Botswana', 'A'),
    (94, 'Who was the first President of the United States?', 'Thomas Jefferson', 'George Washington', 'John Adams', 'Abraham Lincoln', 'B'),
    (95, 'How many seconds are in a minute?', '30', '45', '60', '90', 'C'),
    (96, 'Which scientist is famous for studying electricity and flying a kite in a storm?', 'Benjamin Franklin', 'Thomas Edison', 'Nikola Tesla', 'Michael Faraday', 'A'),
    (97, 'What is the capital of Belgium?', 'Brussels', 'Antwerp', 'Bruges', 'Ghent', 'A'),
    (98, 'Which animal is known as the King of the Jungle?', 'Tiger', 'Elephant', 'Lion', 'Bear', 'C'),
    (99, 'What is the largest continent by land area?', 'Africa', 'Asia', 'North America', 'Europe', 'B'),
    (100, 'Which country is famous for the city of Venice?', 'Italy', 'France', 'Croatia', 'Austria', 'A');

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
FROM tmp_general_knowledge_questions_100 t
WHERE NOT EXISTS (
    SELECT 1
    FROM quiz_questions q
    WHERE q.owner_admin_id = @owner_admin_id
      AND q.question_text = t.question_text
);

DROP TEMPORARY TABLE tmp_general_knowledge_questions_100;

COMMIT;
