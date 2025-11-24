
 CREATE TABLE car_categories (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);


CREATE TABLE cars (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    car_name VARCHAR(100) NOT NULL,
    car_model VARCHAR(100),
    car_image_url TEXT,
    car_category INT REFERENCES car_category(id),   -- foreign key to car_category
    is_active BOOLEAN DEFAULT TRUE
);

ALTER TABLE `cars`
ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;


CREATE TABLE car_additional_details (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    car_id BIGINT UNSIGNED NOT NULL,
    no_of_seats INT NOT NULL
);


ALTER TABLE `car_additional_details`
ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE car_additional_details
ADD COLUMN amenity TEXT;

CREATE TABLE amenities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);


INSERT INTO amenities (name) VALUES
('Air Conditioning'),
('Automatic Transmission'),
('GPS / Navigation'),
('Bluetooth'),
('Leather Seats'),
('Heated Seats'),
('Child Seat'),
('Cruise Control'),
('Backup Camera'),
('Parking Sensors');


CREATE TABLE car_price_details (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    car_id BIGINT  NOT NULL,
    price_type ENUM('day', 'week', 'trip') NOT NULL,
    min_hours INT DEFAULT 0,
    CONSTRAINT fk_additional_car FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);

ALTER TABLE `car_price_details`
ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE car_price_details
ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0;

CREATE TABLE car_discount_price_details (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    car_id BIGINT  NOT NULL,
    price_type ENUM('day', 'week', 'trip') NOT NULL,
    price DECIMAL(10,2) NOT NULL,

    CONSTRAINT fk_discount_car FOREIGN KEY (car_id)
        REFERENCES cars(id)
);

ALTER TABLE `car_discount_price_details`
ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;


CREATE TABLE price_type (
    id INT NOT NULL AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL,
    PRIMARY KEY (id)
);

INSERT INTO price_type (type_name) VALUES
('day'),
('week'),
('month'),
('hour'),
('trip'),
('km');