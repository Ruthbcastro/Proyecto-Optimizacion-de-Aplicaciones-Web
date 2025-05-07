CREATE DATABASE rss_reader;

USE rss_reader;

CREATE TABLE feeds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(255) NOT NULL
);

CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    link VARCHAR(255),
    description TEXT,
    pub_date DATETIME,
    category VARCHAR(255),
    feed_id INT,
    FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE
);
