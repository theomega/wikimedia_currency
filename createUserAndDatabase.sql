CREATE DATABASE wikimedia;
CREATE USER 'wikimedia'@'localhost' IDENTIFIED BY 'wikimedia';
GRANT ALL PRIVILEGES ON wikimedia.* TO 'wikimedia'@'localhost';
