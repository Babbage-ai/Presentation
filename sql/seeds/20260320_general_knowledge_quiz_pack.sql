USE cloud_signage_present;

START TRANSACTION;

SET @owner_admin_id := 1;
SET @playlist_name := 'General Knowledge Quiz Pack 2';
SET @countdown_seconds := 12;
SET @reveal_duration := 6;
SET @created_at := UTC_TIMESTAMP();

INSERT INTO playlists (owner_admin_id, name, active, created_at, updated_at)
SELECT @owner_admin_id, @playlist_name, 1, @created_at, @created_at
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM playlists
    WHERE owner_admin_id = @owner_admin_id
      AND name = @playlist_name
);

SET @playlist_id := (
    SELECT id
    FROM playlists
    WHERE owner_admin_id = @owner_admin_id
      AND name = @playlist_name
    ORDER BY id DESC
    LIMIT 1
);

CREATE TEMPORARY TABLE tmp_general_knowledge_questions (
    sort_order INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL
);

INSERT INTO tmp_general_knowledge_questions
    (sort_order, question_text, option_a, option_b, option_c, option_d, correct_option)
VALUES
    (1, 'What is the capital city of Canada?', 'Toronto', 'Ottawa', 'Vancouver', 'Montreal', 'B'),
    (2, 'Which planet is known as the Red Planet?', 'Mars', 'Jupiter', 'Venus', 'Mercury', 'A'),
    (3, 'Who painted the Mona Lisa?', 'Vincent van Gogh', 'Leonardo da Vinci', 'Pablo Picasso', 'Claude Monet', 'B'),
    (4, 'What is the largest ocean on Earth?', 'Atlantic Ocean', 'Indian Ocean', 'Arctic Ocean', 'Pacific Ocean', 'D'),
    (5, 'How many continents are there?', 'Five', 'Six', 'Seven', 'Eight', 'C'),
    (6, 'What is the chemical symbol for gold?', 'Ag', 'Gd', 'Au', 'Go', 'C'),
    (7, 'Which animal is the largest mammal in the world?', 'African elephant', 'Blue whale', 'Giraffe', 'Hippopotamus', 'B'),
    (8, 'Which country is home to the Great Pyramid of Giza?', 'Jordan', 'Mexico', 'Egypt', 'Peru', 'C'),
    (9, 'Who wrote Romeo and Juliet?', 'Charles Dickens', 'William Shakespeare', 'Jane Austen', 'Mark Twain', 'B'),
    (10, 'What is H2O more commonly known as?', 'Salt', 'Hydrogen peroxide', 'Water', 'Oxygen', 'C'),
    (11, 'Which gas do plants absorb from the atmosphere?', 'Oxygen', 'Nitrogen', 'Carbon dioxide', 'Helium', 'C'),
    (12, 'What is the tallest mountain above sea level?', 'K2', 'Mount Kilimanjaro', 'Mount Everest', 'Denali', 'C'),
    (13, 'Which country gifted the Statue of Liberty to the United States?', 'France', 'Spain', 'Italy', 'Germany', 'A'),
    (14, 'What is the hardest natural substance?', 'Iron', 'Diamond', 'Quartz', 'Granite', 'B'),
    (15, 'Which instrument has 88 keys?', 'Violin', 'Flute', 'Piano', 'Trumpet', 'C'),
    (16, 'In which sport would you perform a slam dunk?', 'Tennis', 'Basketball', 'Volleyball', 'Baseball', 'B'),
    (17, 'What is the main language spoken in Brazil?', 'Spanish', 'Portuguese', 'French', 'English', 'B'),
    (18, 'Which desert is the largest hot desert in the world?', 'Gobi Desert', 'Arabian Desert', 'Kalahari Desert', 'Sahara Desert', 'D'),
    (19, 'Who was the first person to walk on the Moon?', 'Buzz Aldrin', 'Yuri Gagarin', 'Neil Armstrong', 'Michael Collins', 'C'),
    (20, 'Which organ pumps blood through the human body?', 'Liver', 'Lungs', 'Kidney', 'Heart', 'D'),
    (21, 'What is the smallest prime number?', '0', '1', '2', '3', 'C'),
    (22, 'Which country has Tokyo as its capital?', 'China', 'South Korea', 'Thailand', 'Japan', 'D'),
    (23, 'What do bees primarily collect from flowers?', 'Sand', 'Nectar', 'Oil', 'Dew', 'B'),
    (24, 'Which continent is the Sahara Desert located on?', 'Asia', 'South America', 'Africa', 'Australia', 'C'),
    (25, 'Who discovered penicillin?', 'Marie Curie', 'Louis Pasteur', 'Alexander Fleming', 'Isaac Newton', 'C'),
    (26, 'Which is the longest river in South America?', 'Amazon River', 'Parana River', 'Orinoco River', 'Madeira River', 'A'),
    (27, 'What is the freezing point of water in Celsius?', '0', '32', '10', '100', 'A'),
    (28, 'Which country is famous for the maple leaf symbol?', 'Canada', 'Sweden', 'Norway', 'Finland', 'A'),
    (29, 'Which metal is liquid at room temperature?', 'Mercury', 'Aluminum', 'Copper', 'Silver', 'A'),
    (30, 'How many days are in a leap year?', '365', '366', '364', '367', 'B'),
    (31, 'Which planet has the most prominent ring system?', 'Mars', 'Saturn', 'Neptune', 'Earth', 'B'),
    (32, 'What is the currency of the United Kingdom?', 'Euro', 'Dollar', 'Pound sterling', 'Franc', 'C'),
    (33, 'Who wrote The Hobbit?', 'J.R.R. Tolkien', 'C.S. Lewis', 'George Orwell', 'J.K. Rowling', 'A'),
    (34, 'Which blood type is known as the universal donor for red cells?', 'AB positive', 'O negative', 'A positive', 'B negative', 'B'),
    (35, 'Which country is both a continent and a nation?', 'Australia', 'Greenland', 'Iceland', 'Madagascar', 'A'),
    (36, 'How many players are on a standard soccer team on the field at one time?', '9', '10', '11', '12', 'C'),
    (37, 'What is the boiling point of water at sea level in Celsius?', '90', '95', '100', '110', 'C'),
    (38, 'Which scientist proposed the theory of relativity?', 'Albert Einstein', 'Galileo Galilei', 'Nikola Tesla', 'Stephen Hawking', 'A'),
    (39, 'Which city is known as the City of Light?', 'Rome', 'Paris', 'Lisbon', 'Vienna', 'B'),
    (40, 'What is the largest planet in our solar system?', 'Earth', 'Saturn', 'Jupiter', 'Uranus', 'C'),
    (41, 'Which is the fastest land animal?', 'Cheetah', 'Lion', 'Pronghorn', 'Greyhound', 'A'),
    (42, 'How many sides does a hexagon have?', 'Five', 'Six', 'Seven', 'Eight', 'B'),
    (43, 'Which country is famous for the Taj Mahal?', 'India', 'Pakistan', 'Nepal', 'Bangladesh', 'A'),
    (44, 'What is the primary ingredient in guacamole?', 'Cucumber', 'Avocado', 'Spinach', 'Peas', 'B'),
    (45, 'Which planet is closest to the Sun?', 'Mercury', 'Venus', 'Earth', 'Mars', 'A'),
    (46, 'Who composed the Four Seasons?', 'Mozart', 'Beethoven', 'Vivaldi', 'Bach', 'C'),
    (47, 'Which ocean lies on the east coast of the United States?', 'Pacific Ocean', 'Indian Ocean', 'Southern Ocean', 'Atlantic Ocean', 'D'),
    (48, 'What is the capital city of Spain?', 'Barcelona', 'Madrid', 'Seville', 'Valencia', 'B'),
    (49, 'How many teeth does a typical adult human have?', '28', '30', '32', '34', 'C'),
    (50, 'Which bird is often associated with delivering messages in wartime history?', 'Crow', 'Pigeon', 'Sparrow', 'Falcon', 'B');

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
FROM tmp_general_knowledge_questions t;

INSERT INTO playlist_items
    (playlist_id, item_type, media_id, quiz_question_id, sort_order, image_duration, active, created_at)
SELECT
    @playlist_id,
    'quiz',
    NULL,
    q.id,
    t.sort_order,
    @countdown_seconds,
    1,
    @created_at
FROM tmp_general_knowledge_questions t
INNER JOIN quiz_questions q
    ON q.owner_admin_id = @owner_admin_id
   AND q.question_text = t.question_text
   AND q.created_at = @created_at
ORDER BY t.sort_order ASC;

DROP TEMPORARY TABLE tmp_general_knowledge_questions;

COMMIT;
