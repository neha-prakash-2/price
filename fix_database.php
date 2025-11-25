<?php
// fix_database.php
require __DIR__ . '/db_connect.php';

try {
    echo "Attempting to update database structure...<br>";

    // SQL command to add the missing columns if they don't exist
    $sql = "
        DO $$ 
        BEGIN 
            BEGIN
                ALTER TABLE users ADD COLUMN reset_token_hash VARCHAR(64) NULL;
            EXCEPTION
                WHEN duplicate_column THEN RAISE NOTICE 'column reset_token_hash already exists in users.';
            END;

            BEGIN
                ALTER TABLE users ADD COLUMN reset_token_expires_at TIMESTAMP NULL;
            EXCEPTION
                WHEN duplicate_column THEN RAISE NOTICE 'column reset_token_expires_at already exists in users.';
            END;
        END $$;
    ";

    // Execute the query
    $pdo->exec($sql);
    
    echo "<h3 style='color:green'>Success!</h3>";
    echo "Columns 'reset_token_hash' and 'reset_token_expires_at' have been added to the 'users' table.<br>";
    echo "You can now delete this file and try <a href='forgot_password.php'>forgot_password.php</a> again.";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Error</h3>";
    echo "Failed to update table: " . $e->getMessage();
}
?>
