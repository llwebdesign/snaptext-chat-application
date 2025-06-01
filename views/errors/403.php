<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden | SnapText</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 font-[Inter] min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full p-8 bg-white rounded-lg shadow-lg text-center">
        <h1 class="text-6xl font-bold text-gray-900 mb-4">403</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Access Denied</h2>
        <p class="text-gray-600 mb-8">
            Sorry, you don't have permission to access this page.
        </p>
        <div class="space-y-4">
            <a href="/" 
               class="block w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                Go to Home
            </a>
            <button onclick="history.back()" 
                    class="block w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                Go Back
            </button>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="/login.php" 
               class="block w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                Login
            </a>
            <?php endif; ?>
        </div>
        <p class="mt-8 text-sm text-gray-500">
            If you believe this is an error, please contact support.
        </p>
    </div>
</body>
</html>
