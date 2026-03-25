USE cloud_signage_present;

START TRANSACTION;

SET @owner_admin_id := 1;
SET @countdown_seconds := 12;
SET @reveal_duration := 6;
SET @created_at := UTC_TIMESTAMP();

CREATE TEMPORARY TABLE tmp_geography_questions_100 (
    sort_order INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL
);

INSERT INTO tmp_geography_questions_100
    (sort_order, question_text, option_a, option_b, option_c, option_d, correct_option)
VALUES
    (1, 'What is the capital of Canada?', 'Toronto', 'Ottawa', 'Vancouver', 'Montreal', 'B'),
    (2, 'Which river is the longest in the world by the measurement commonly used in general quizzes?', 'Amazon', 'Yangtze', 'Nile', 'Mississippi', 'C'),
    (3, 'Which country has the largest land area?', 'Canada', 'China', 'United States', 'Russia', 'D'),
    (4, 'What is the capital of Australia?', 'Sydney', 'Melbourne', 'Canberra', 'Perth', 'C'),
    (5, 'Which desert covers much of northern Africa?', 'Gobi', 'Sahara', 'Arabian', 'Kalahari', 'B'),
    (6, 'Mount Kilimanjaro is located in which country?', 'Kenya', 'Uganda', 'Tanzania', 'Ethiopia', 'C'),
    (7, 'What is the capital of Brazil?', 'Rio de Janeiro', 'Brasilia', 'Sao Paulo', 'Salvador', 'B'),
    (8, 'Which ocean lies between Africa and Australia?', 'Atlantic Ocean', 'Indian Ocean', 'Pacific Ocean', 'Arctic Ocean', 'B'),
    (9, 'Which country is home to the city of Barcelona?', 'Portugal', 'Italy', 'Spain', 'France', 'C'),
    (10, 'What is the smallest country in the world by area?', 'Monaco', 'San Marino', 'Vatican City', 'Liechtenstein', 'C'),
    (11, 'Which U.S. state is known as the Aloha State?', 'Florida', 'California', 'Hawaii', 'Alaska', 'C'),
    (12, 'What is the capital of Egypt?', 'Alexandria', 'Cairo', 'Giza', 'Luxor', 'B'),
    (13, 'Which mountain range forms a natural border between France and Spain?', 'Alps', 'Andes', 'Pyrenees', 'Carpathians', 'C'),
    (14, 'Which continent is the driest inhabited continent?', 'Africa', 'Australia', 'Europe', 'South America', 'B'),
    (15, 'What is the capital of Argentina?', 'Buenos Aires', 'Cordoba', 'Santiago', 'Montevideo', 'A'),
    (16, 'Which country has the most people in the world?', 'India', 'China', 'United States', 'Indonesia', 'A'),
    (17, 'The city of Dubrovnik is in which country?', 'Croatia', 'Slovenia', 'Montenegro', 'Serbia', 'A'),
    (18, 'Which sea separates Saudi Arabia from northeast Africa?', 'Arabian Sea', 'Red Sea', 'Black Sea', 'Caspian Sea', 'B'),
    (19, 'What is the capital of Thailand?', 'Bangkok', 'Chiang Mai', 'Phuket', 'Pattaya', 'A'),
    (20, 'Which European river flows through Budapest?', 'Danube', 'Seine', 'Rhine', 'Po', 'A'),
    (21, 'What is the largest island in the Caribbean?', 'Jamaica', 'Cuba', 'Hispaniola', 'Puerto Rico', 'B'),
    (22, 'Which country is famous for the fjords of western Europe?', 'Sweden', 'Finland', 'Norway', 'Iceland', 'C'),
    (23, 'What is the capital of Turkey?', 'Istanbul', 'Ankara', 'Izmir', 'Bursa', 'B'),
    (24, 'Which country contains the city of Marrakech?', 'Morocco', 'Algeria', 'Tunisia', 'Libya', 'A'),
    (25, 'Lake Titicaca lies on the border of Peru and which other country?', 'Chile', 'Paraguay', 'Bolivia', 'Ecuador', 'C'),
    (26, 'What is the capital of Vietnam?', 'Ho Chi Minh City', 'Hanoi', 'Da Nang', 'Hue', 'B'),
    (27, 'Which mountain is the highest in the world above sea level?', 'K2', 'Kangchenjunga', 'Mount Everest', 'Lhotse', 'C'),
    (28, 'Which country is directly south of the United States?', 'Guatemala', 'Mexico', 'Belize', 'Cuba', 'B'),
    (29, 'What is the capital of Kenya?', 'Mombasa', 'Nairobi', 'Kisumu', 'Dodoma', 'B'),
    (30, 'Which continent contains the Atacama Desert?', 'Africa', 'Asia', 'South America', 'Australia', 'C'),
    (31, 'Which country is made up of thousands of islands and has Jakarta as its capital?', 'Philippines', 'Malaysia', 'Indonesia', 'Thailand', 'C'),
    (32, 'What is the capital of Sweden?', 'Stockholm', 'Gothenburg', 'Oslo', 'Malmo', 'A'),
    (33, 'Which river flows through Paris?', 'Rhine', 'Loire', 'Seine', 'Danube', 'C'),
    (34, 'Which country is bordered by both Spain and France?', 'Andorra', 'Belgium', 'Austria', 'Luxembourg', 'A'),
    (35, 'What is the capital of Peru?', 'Cusco', 'Lima', 'Quito', 'La Paz', 'B'),
    (36, 'Which African country has Cape Town as one of its capitals?', 'Namibia', 'South Africa', 'Botswana', 'Zimbabwe', 'B'),
    (37, 'Which U.S. state is the largest by area?', 'Texas', 'California', 'Alaska', 'Montana', 'C'),
    (38, 'What is the capital of New Zealand?', 'Auckland', 'Wellington', 'Christchurch', 'Hamilton', 'B'),
    (39, 'Which desert is found mainly in Mongolia and northern China?', 'Kalahari', 'Namib', 'Arabian', 'Gobi', 'D'),
    (40, 'Which country has the city of Prague?', 'Poland', 'Austria', 'Czech Republic', 'Slovakia', 'C'),
    (41, 'What is the capital of Nigeria?', 'Lagos', 'Abuja', 'Kano', 'Accra', 'B'),
    (42, 'Which European country is famous for the Matterhorn?', 'Austria', 'France', 'Switzerland', 'Germany', 'C'),
    (43, 'Which two countries share the Iberian Peninsula?', 'Spain and Portugal', 'France and Spain', 'Italy and Spain', 'Portugal and Morocco', 'A'),
    (44, 'What is the capital of Chile?', 'Valparaiso', 'Santiago', 'Lima', 'Quito', 'B'),
    (45, 'Which country has the largest population in Africa?', 'Nigeria', 'Egypt', 'Ethiopia', 'South Africa', 'A'),
    (46, 'Which body of water lies between the United Kingdom and mainland Europe?', 'Baltic Sea', 'English Channel', 'North Sea', 'Irish Sea', 'B'),
    (47, 'What is the capital of Pakistan?', 'Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'C'),
    (48, 'Which country is home to the city of Kyoto?', 'China', 'South Korea', 'Japan', 'Thailand', 'C'),
    (49, 'Which mountain range runs along the western edge of South America?', 'Rockies', 'Andes', 'Alps', 'Himalayas', 'B'),
    (50, 'What is the capital of the Netherlands?', 'Rotterdam', 'The Hague', 'Amsterdam', 'Utrecht', 'C'),
    (51, 'Which African lake is the largest by area?', 'Lake Tanganyika', 'Lake Victoria', 'Lake Malawi', 'Lake Turkana', 'B'),
    (52, 'Which country has the city of Reykjavik?', 'Norway', 'Finland', 'Iceland', 'Denmark', 'C'),
    (53, 'What is the capital of Colombia?', 'Bogota', 'Medellin', 'Quito', 'Cali', 'A'),
    (54, 'Which country is home to Mount Fuji?', 'China', 'Japan', 'Nepal', 'South Korea', 'B'),
    (55, 'Which sea is shrinking and lies between Kazakhstan and Uzbekistan?', 'Caspian Sea', 'Aral Sea', 'Black Sea', 'Dead Sea', 'B'),
    (56, 'What is the capital of Austria?', 'Salzburg', 'Vienna', 'Graz', 'Innsbruck', 'B'),
    (57, 'Which country has the city of Nairobi?', 'Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'A'),
    (58, 'Which strait separates Asia and North America?', 'Strait of Gibraltar', 'Bering Strait', 'Bosporus', 'Strait of Malacca', 'B'),
    (59, 'What is the capital of Greece?', 'Athens', 'Thessaloniki', 'Patras', 'Heraklion', 'A'),
    (60, 'Which country contains the ancient site of Petra?', 'Jordan', 'Egypt', 'Israel', 'Lebanon', 'A'),
    (61, 'Which continent has the fewest countries?', 'South America', 'Europe', 'Australia/Oceania', 'North America', 'C'),
    (62, 'What is the capital of Hungary?', 'Prague', 'Budapest', 'Bratislava', 'Belgrade', 'B'),
    (63, 'Which country has the city of Geneva?', 'France', 'Belgium', 'Switzerland', 'Luxembourg', 'C'),
    (64, 'Which river flows through Cairo?', 'Nile', 'Niger', 'Congo', 'Zambezi', 'A'),
    (65, 'What is the capital of Saudi Arabia?', 'Jeddah', 'Mecca', 'Riyadh', 'Dammam', 'C'),
    (66, 'Which country is known as the Land of a Thousand Lakes?', 'Sweden', 'Finland', 'Norway', 'Iceland', 'B'),
    (67, 'Which U.S. state has Phoenix as its capital?', 'Nevada', 'Arizona', 'New Mexico', 'Utah', 'B'),
    (68, 'What is the capital of Denmark?', 'Aarhus', 'Copenhagen', 'Odense', 'Oslo', 'B'),
    (69, 'Which country has the city of Casablanca?', 'Morocco', 'Tunisia', 'Algeria', 'Egypt', 'A'),
    (70, 'Which desert lies in southern Africa and extends into Namibia and Botswana?', 'Gobi', 'Kalahari', 'Sahara', 'Patagonian', 'B'),
    (71, 'What is the capital of the Philippines?', 'Cebu', 'Manila', 'Davao', 'Quezon City', 'B'),
    (72, 'Which country has the city of Bruges?', 'Belgium', 'Netherlands', 'Luxembourg', 'France', 'A'),
    (73, 'Which ocean is on the east coast of the United States?', 'Pacific Ocean', 'Indian Ocean', 'Atlantic Ocean', 'Arctic Ocean', 'C'),
    (74, 'What is the capital of Portugal?', 'Porto', 'Lisbon', 'Braga', 'Coimbra', 'B'),
    (75, 'Which country contains the city of Zanzibar City?', 'Kenya', 'Tanzania', 'Mozambique', 'Madagascar', 'B'),
    (76, 'Which mountain range includes Mont Blanc?', 'Apennines', 'Alps', 'Pyrenees', 'Balkans', 'B'),
    (77, 'What is the capital of Ethiopia?', 'Addis Ababa', 'Asmara', 'Nairobi', 'Kampala', 'A'),
    (78, 'Which country is an island nation in the Indian Ocean with Colombo and Sri Jayawardenepura Kotte?', 'Maldives', 'Sri Lanka', 'Mauritius', 'Seychelles', 'B'),
    (79, 'Which major river flows through Baghdad?', 'Euphrates', 'Tigris', 'Jordan', 'Indus', 'B'),
    (80, 'What is the capital of Finland?', 'Helsinki', 'Turku', 'Tampere', 'Stockholm', 'A'),
    (81, 'Which country contains the city of Split?', 'Croatia', 'Slovenia', 'Bosnia and Herzegovina', 'Montenegro', 'A'),
    (82, 'Which sea separates Europe from Asia at Istanbul?', 'Aegean Sea', 'Black Sea', 'Sea of Marmara', 'Adriatic Sea', 'C'),
    (83, 'What is the capital of Ireland?', 'Cork', 'Dublin', 'Galway', 'Limerick', 'B'),
    (84, 'Which country has the city of Lahore?', 'India', 'Pakistan', 'Bangladesh', 'Afghanistan', 'B'),
    (85, 'Which is the largest country in South America by area?', 'Argentina', 'Brazil', 'Peru', 'Colombia', 'B'),
    (86, 'What is the capital of Malaysia?', 'Kuala Lumpur', 'George Town', 'Johor Bahru', 'Malacca City', 'A'),
    (87, 'Which country contains the island of Sicily?', 'Greece', 'Italy', 'Spain', 'Croatia', 'B'),
    (88, 'Which waterfall on the Zambia-Zimbabwe border is one of the world''s largest?', 'Angel Falls', 'Victoria Falls', 'Iguazu Falls', 'Niagara Falls', 'B'),
    (89, 'What is the capital of Norway?', 'Oslo', 'Bergen', 'Stavanger', 'Trondheim', 'A'),
    (90, 'Which country has the city of Cusco?', 'Peru', 'Bolivia', 'Chile', 'Ecuador', 'A'),
    (91, 'Which country contains the Sinai Peninsula?', 'Israel', 'Jordan', 'Egypt', 'Saudi Arabia', 'C'),
    (92, 'What is the capital of South Korea?', 'Seoul', 'Busan', 'Incheon', 'Daegu', 'A'),
    (93, 'Which country has the city of Medellin?', 'Venezuela', 'Colombia', 'Ecuador', 'Panama', 'B'),
    (94, 'Which inland body of water is the largest in the world by area?', 'Aral Sea', 'Lake Superior', 'Caspian Sea', 'Lake Victoria', 'C'),
    (95, 'What is the capital of Belgium?', 'Antwerp', 'Brussels', 'Bruges', 'Ghent', 'B'),
    (96, 'Which country contains the region of Patagonia along with Argentina?', 'Chile', 'Uruguay', 'Paraguay', 'Bolivia', 'A'),
    (97, 'Which African country is immediately south of Egypt?', 'Sudan', 'Chad', 'Eritrea', 'Libya', 'A'),
    (98, 'What is the capital of Switzerland?', 'Zurich', 'Geneva', 'Bern', 'Basel', 'C'),
    (99, 'Which country has the city of Istanbul?', 'Greece', 'Turkey', 'Bulgaria', 'Romania', 'B'),
    (100, 'Which South Asian river is sacred in Hinduism and flows through northern India?', 'Indus', 'Brahmaputra', 'Ganges', 'Godavari', 'C');

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
FROM tmp_geography_questions_100 t
WHERE NOT EXISTS (
    SELECT 1
    FROM quiz_questions q
    WHERE q.owner_admin_id = @owner_admin_id
      AND q.question_text = t.question_text
);

DROP TEMPORARY TABLE tmp_geography_questions_100;

COMMIT;
