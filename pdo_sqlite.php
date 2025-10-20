<?php

try {
    // Kết nối đến SQLite database
    $pdo = new PDO('sqlite:messages.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to the SQLite database successfully.\n";

    $createUsersTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            firstname TEXT,
            lastname TEXT,
            status INTEGER DEFAULT 1,
            avatar TEXT,
            bio TEXT,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $pdo->exec($createUsersTable);
    echo "Table users created successfully.\n";

    $createMessagesTable = "
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            hidden_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        );
    ";
    $pdo->exec($createMessagesTable);
    echo "Table messages created successfully.\n";

    $createTokenResetTable = "
        CREATE TABLE IF NOT EXISTS token_reset (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        );
    ";
    $pdo->exec($createTokenResetTable);
    echo "Table created successfully.\n";

    $insertUser = "
        INSERT INTO users (username, email, password, firstname, lastname, status)
        VALUES ('demo', 'demo@example.com', 'demo', 'John', 'Doe', 1);
    ";
    $pdo->exec($insertUser);
    echo "Example user added successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
