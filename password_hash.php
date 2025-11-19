<?php
// Initialize variables to avoid errors on first load
$originalPassword = '';
$hashedPassword = '';

// Check if the form has been submitted using the POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if the 'password' input field is not empty
    if (!empty($_POST['password'])) {
        // Get the password from the form
        $originalPassword = $_POST['password'];
        
        // Hash the password using PHP's recommended default algorithm
        $hashedPassword = password_hash($originalPassword, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hashing Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans p-8">

    <div class="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow-md">
    
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Password Hashing Page 🔐</h1>
        <p class="text-gray-600 mb-6">Enter a password in the field below to generate a secure hash.</p>
        
        <form action="" method="post">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700"><b>Enter Password:</b></label>
                <input 
                    type="text" 
                    id="password" 
                    name="password" 
                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    required
                >
            </div>
            <button 
                type="submit" 
                class="mt-4 w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
            >
                Hash Password
            </button>
        </form>

        <?php
        // Only display the result box if a hash has been generated
        if ($hashedPassword) {
            echo '<div class="mt-6 p-4 bg-gray-50 border rounded-md break-words">';
            
            // Use htmlspecialchars to prevent XSS attacks when displaying user input
            echo '<span class="block text-sm font-medium text-gray-500">Original Password:</span>';
            echo '<p class="font-mono text-gray-800">' . htmlspecialchars($originalPassword) . '</p>';
            
            echo '<span class="block text-sm font-medium text-gray-500 mt-3">Hashed Password:</span>';
            echo '<p class="font-mono text-gray-800">' . htmlspecialchars($hashedPassword) . '</p>';
            
            echo '</div>';
        }
        ?>
    </div>

</body>
</html>