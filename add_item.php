<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item - RenTayo</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #0ea5e9, #06b6d4);
            border-radius: 4px;
        }

        /* Layout: footer pinned below content, full-width */
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; }
        .page-content { flex: 1 0 auto; }
        .site-footer-wrapper { 
            flex: 0 0 auto;
            margin-top: 2rem;
            width: 100%;
            padding: 0;
            box-sizing: border-box;
            position: relative;
            z-index: 0;
            background: transparent;
        }
        /* Make included footer edge-to-edge */
        .site-footer-wrapper > * {
            max-width: none !important;
            width: 100% !important;
            margin: 0 !important;
            position: static !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        /* Extra breathing room below */
        body { padding-bottom: 0; }

        /* Footer layout: wrapper spans full width; included footer content is centered.
           Use non-intrusive positioning (no forced z-index) to avoid overlapping other UI. */
        .site-footer-wrapper { 
            margin-top: 2rem; 
            width: 100%; 
            padding: 1rem 0; 
            box-sizing: border-box; 
            position: relative; 
            z-index: 0; /* keep footer behind interactive overlays by default */
            background: transparent;
        }
        /* Constrain and center the inner footer content coming from footer.php */
        .site-footer-wrapper > * { 
            max-width: 64rem; 
            margin-left: auto; 
            margin-right: auto; 
            position: static !important; 
            width: auto !important; 
        }
        /* Give the page bottom breathing room so footer doesn't overlap fixed content */
        body { padding-bottom: 5rem; }
    </style>
</head>
<body class="bg-gradient-to-br from-sky-50 via-white to-blue-50 min-h-screen">
    
    <!-- Decorative background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-20 right-20 w-96 h-96 bg-sky-200 rounded-full blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute bottom-20 left-20 w-96 h-96 bg-cyan-200 rounded-full blur-3xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <!-- NAVBAR PLACEHOLDER -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="page-content relative z-10 max-w-5xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 bg-gradient-to-br from-sky-500 to-cyan-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i data-lucide="plus-circle" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-bold text-gray-900">Add New Item</h1>
                    <p class="text-gray-600">List an item for rent on RenTayo</p>
                </div>
            </div>
            <a href="my_items.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-sky-50 border-2 border-sky-200 rounded-xl text-sky-600 font-medium transition-all group">
                <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i>
                Back to My Items
            </a>
        </div>

        <!-- Form Card -->
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl shadow-2xl shadow-sky-200/50 border-2 border-sky-100 overflow-hidden">
            
            <!-- Image Upload Section -->
            <div class="bg-gradient-to-br from-sky-50 to-cyan-50 p-8 border-b-2 border-sky-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <i data-lucide="image" class="w-6 h-6 text-sky-600"></i>
                    Item Images
                </h2>
                
                <div class="space-y-4">
                    <!-- Main Image Upload -->
                    <div class="relative">
                        <input type="file" id="itemImages" name="item_images[]" accept="image/*" multiple class="hidden">
                        <label for="itemImages" class="flex flex-col items-center justify-center h-64 bg-white border-3 border-dashed border-sky-300 rounded-2xl cursor-pointer hover:border-sky-500 hover:bg-sky-50 transition-all group">
                            <div class="text-center">
                                <div class="w-20 h-20 bg-gradient-to-br from-sky-100 to-cyan-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                                    <i data-lucide="upload" class="w-10 h-10 text-sky-600"></i>
                                </div>
                                <p class="text-lg font-semibold text-gray-900 mb-2">Click to upload images</p>
                                <p class="text-sm text-gray-600">PNG, JPG up to 5MB (Max 5 images)</p>
                            </div>
                        </label>
                    </div>

                    <!-- Image Preview Grid -->
                    <div id="imagePreview" class="hidden grid grid-cols-2 md:grid-cols-5 gap-4">
                        <!-- Preview items will be inserted here by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Form Fields -->
            <div class="p-8 space-y-6">
                
                <!-- Item Title -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Item Title <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="title" 
                        required 
                        placeholder="e.g., MacBook Pro 13-inch"
                        class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all"
                    >
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        name="description" 
                        required 
                        rows="4"
                        placeholder="Describe your item, its condition, and any special features..."
                        class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all resize-none"
                    ></textarea>
                </div>

                <!-- Price and Quantity Row -->
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Price -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Price per Day <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="px-4 py-3 bg-gradient-to-br from-sky-50 to-cyan-50 border-2 border-sky-200 rounded-xl text-sky-600 font-bold">₱</span>
                            <input 
                                type="number" 
                                name="price_per_day" 
                                required 
                                min="1" 
                                step="0.01"
                                placeholder="50.00"
                                class="flex-1 px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all"
                            >
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Quantity Available <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            name="quantity" 
                            required 
                            min="1"
                            value="1"
                            class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-sky-500 focus:bg-white transition-all"
                        >
                    </div>
                </div>

                <!-- Categories -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                        Categories <span class="text-red-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <!-- <?php foreach($categories as $cat): ?> -->
                        <label class="flex items-center gap-3 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-sky-500 hover:bg-sky-50 transition-all group">
                            <input type="checkbox" name="categories[]" value="1" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500">
                            <span class="font-medium text-gray-700 group-hover:text-sky-600">Electronics</span>
                        </label>
                        
                        <label class="flex items-center gap-3 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-sky-500 hover:bg-sky-50 transition-all group">
                            <input type="checkbox" name="categories[]" value="2" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500">
                            <span class="font-medium text-gray-700 group-hover:text-sky-600">Books</span>
                        </label>
                        
                        <label class="flex items-center gap-3 p-4 bg-gray-50 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-sky-500 hover:bg-sky-50 transition-all group">
                            <input type="checkbox" name="categories[]" value="3" class="w-5 h-5 text-sky-600 rounded focus:ring-sky-500">
                            <span class="font-medium text-gray-700 group-hover:text-sky-600">Sports</span>
                        </label>
                        <!-- <?php endforeach; ?> -->
                    </div>
                </div>

                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                <div class="p-4 bg-red-50 border-2 border-red-200 rounded-xl flex items-center gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                    <p class="text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="p-4 bg-emerald-50 border-2 border-emerald-200 rounded-xl flex items-center gap-3">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                    <p class="text-emerald-700 font-medium"><?php echo htmlspecialchars($success); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="bg-gradient-to-br from-sky-50 to-cyan-50 p-6 border-t-2 border-sky-100">
                <div class="flex flex-col sm:flex-row gap-4 justify-end">
                    <a href="my_items.php" class="px-6 py-3 bg-white hover:bg-gray-50 text-gray-700 font-semibold rounded-xl border-2 border-gray-200 transition-all text-center">
                        Cancel
                    </a>
                    <button type="submit" class="px-8 py-3 bg-gradient-to-r from-sky-500 to-cyan-600 hover:from-sky-600 hover:to-cyan-700 text-white font-bold rounded-xl shadow-lg shadow-sky-500/30 transition-all hover:scale-105 flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-5 h-5"></i>
                        Add Item
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- FOOTER PLACEHOLDER -->
    <div class="site-footer-wrapper">
        <?php include 'footer.php'; ?>
    </div>

    <script>
        lucide.createIcons();

        // Image Preview Handler
        document.getElementById('itemImages').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('imagePreview');
            previewContainer.innerHTML = '';
            
            if (this.files.length > 0) {
                previewContainer.classList.remove('hidden');
                
                Array.from(this.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'relative group';
                        div.innerHTML = `
                            <img src="${e.target.result}" class="w-full h-32 object-cover rounded-xl border-2 border-sky-200">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-xl flex items-center justify-center">
                                <span class="text-white text-sm font-semibold">${index === 0 ? 'Main Image' : `Image ${index + 1}`}</span>
                            </div>
                        `;
                        previewContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                previewContainer.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
