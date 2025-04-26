<?php
// Include the database connection file
require 'db_connection.php';

// Perform a query
$sql = "SELECT * FROM users"; // Replace 'users' with your table name
$result = $conn->query($sql);

// Check if the query was successful
if ($result->num_rows > 0) {
    // Fetch and display data
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " - Name: " . $row['name'] . "<br>";
    }
} else {
    echo "No results found.";
}

// Close the connection (optional)
closeConnection($conn);
?>