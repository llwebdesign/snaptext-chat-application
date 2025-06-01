<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error | SnapText</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 font-[Inter] min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full p-8 bg-white rounded-lg shadow-lg text-center">
        <h1 class="text-6xl font-bold text-gray-900 mb-4">500</h1>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Internal Server Error</h2>
        <p class="text-gray-600 mb-8">
            Oops! Something went wrong on our end. We're working to fix it.
        </p>
        <div class="space-y-4">
            <a href="/" 
               class="block w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                Go to Home
            </a>
            <button onclick="location.reload()" 
                    class="block w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                Try Again
            </button>
        </div>
        <div class="mt-8 text-sm text-gray-500">
            <p class="mb-2">Error Reference: <?php echo uniqid('err_'); ?></p>
            <p>If this problem persists, please contact our support team.</p>
        </div>

        <?php if (isset($_SERVER['HTTP_REFERER'])): ?>
        <div class="mt-4 text-sm">
            <a href="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER']); ?>" 
               class="text-indigo-600 hover:text-indigo-800">
                ← Return to previous page
            </a>
        </div>
        <?php endif; ?>

        <?php
        if (defined('DEBUG_MODE') && DEBUG_MODE === true):
            $error = error_get_last();
            if ($error):
        ?>
            <div class="mt-8 p-4 bg-red-50 rounded-lg text-left">
                <h3 class="text-red-800 font-semibold mb-2">Debug Information:</h3>
                <pre class="text-xs text-red-700 overflow-x-auto">
                    <?php echo htmlspecialchars(print_r($error, true)); ?>
                </pre>
            </div>
        <?php 
            endif;
        endif;
        ?>
    </div>

    <script>
        // Automatically send error reports to the server
        window.addEventListener('load', function() {
            const errorRef = document.querySelector('p').textContent.match(/err_[a-f0-9]+/)[0];
            fetch('/api/error-report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    errorRef: errorRef,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    timestamp: new Date().toISOString()
                })
            }).catch(console.error); // Silently handle any errors
        });
    </script>
</body>
</html>
