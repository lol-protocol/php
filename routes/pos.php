<?php

/**
 * POS (Point of Sale) Site Routes - Contrastocolor
 * URL patterns for the ecommerce/tagging module
 */

return [
    // Products
    'products.index' => [
        'path' => '/products/',
        'controller' => 'POS\ProductsController@index',
        'methods' => ['GET'],
    ],
    'products.show' => [
        'path' => '/products/{id}/',
        'controller' => 'POS\ProductsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'products.details' => [
        'path' => '/products/{id}/details/',
        'controller' => 'POS\ProductsController@details',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'products.variants' => [
        'path' => '/products/{id}/variants/',
        'controller' => 'POS\ProductsController@variants',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'products.colors' => [
        'path' => '/products/{id}/colors/',
        'controller' => 'POS\ProductsController@colors',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],

    // Categories
    'categories.index' => [
        'path' => '/categories/',
        'controller' => 'POS\CategoriesController@index',
        'methods' => ['GET'],
    ],
    'categories.show' => [
        'path' => '/categories/{category}/',
        'controller' => 'POS\CategoriesController@show',
        'methods' => ['GET'],
        'constraints' => ['category' => '[a-z0-9-]+'],
    ],
    'categories.subcategories' => [
        'path' => '/categories/{category}/subcategories/',
        'controller' => 'POS\CategoriesController@subcategories',
        'methods' => ['GET'],
        'constraints' => ['category' => '[a-z0-9-]+'],
    ],
    'categories.subcategory' => [
        'path' => '/categories/{category}/{subcategory}/',
        'controller' => 'POS\CategoriesController@subcategory',
        'methods' => ['GET'],
        'constraints' => ['category' => '[a-z0-9-]+', 'subcategory' => '[a-z0-9-]+'],
    ],

    // Colors / Contrast
    'colors.index' => [
        'path' => '/colors/',
        'controller' => 'POS\ColorsController@index',
        'methods' => ['GET'],
    ],
    'colors.show' => [
        'path' => '/colors/{id}/',
        'controller' => 'POS\ColorsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'colors.products' => [
        'path' => '/colors/{id}/products/',
        'controller' => 'POS\ColorsController@products',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'contrast_palettes.index' => [
        'path' => '/contrast-palettes/',
        'controller' => 'POS\ContrastPalettesController@index',
        'methods' => ['GET'],
    ],
    'contrast_palettes.show' => [
        'path' => '/contrast-palettes/{id}/',
        'controller' => 'POS\ContrastPalettesController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'contrast.checker' => [
        'path' => '/contrast-checker/',
        'controller' => 'POS\ContrastController@checker',
        'methods' => ['GET'],
    ],

    // Orders
    'orders.index' => [
        'path' => '/orders/',
        'controller' => 'POS\OrdersController@index',
        'methods' => ['GET'],
    ],
    'orders.show' => [
        'path' => '/orders/{id}/',
        'controller' => 'POS\OrdersController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'orders.invoice' => [
        'path' => '/orders/{id}/invoice/',
        'controller' => 'POS\OrdersController@invoice',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'orders.tracking' => [
        'path' => '/orders/{id}/tracking/',
        'controller' => 'POS\OrdersController@tracking',
        'methods' => ['GET'],
        'constraints' => ['id' => '[0-9]+'],
    ],
    'orders.returns' => [
        'path' => '/orders/{id}/returns/',
        'controller' => 'POS\OrdersController@returns',
        'methods' => ['GET', 'POST'],
        'constraints' => ['id' => '[0-9]+'],
    ],

    // Cart and Checkout
    'cart.show' => [
        'path' => '/cart/',
        'controller' => 'POS\CartController@show',
        'methods' => ['GET'],
    ],
    'cart.add' => [
        'path' => '/cart/add/',
        'controller' => 'POS\CartController@add',
        'methods' => ['POST'],
    ],
    'cart.update' => [
        'path' => '/cart/update/',
        'controller' => 'POS\CartController@update',
        'methods' => ['POST'],
    ],
    'cart.remove' => [
        'path' => '/cart/remove/',
        'controller' => 'POS\CartController@remove',
        'methods' => ['POST'],
    ],
    'checkout.index' => [
        'path' => '/checkout/',
        'controller' => 'POS\CheckoutController@index',
        'methods' => ['GET'],
    ],
    'checkout.shipping' => [
        'path' => '/checkout/shipping/',
        'controller' => 'POS\CheckoutController@shipping',
        'methods' => ['GET', 'POST'],
    ],
    'checkout.payment' => [
        'path' => '/checkout/payment/',
        'controller' => 'POS\CheckoutController@payment',
        'methods' => ['GET', 'POST'],
    ],
    'checkout.confirmation' => [
        'path' => '/checkout/confirmation/',
        'controller' => 'POS\CheckoutController@confirmation',
        'methods' => ['GET'],
    ],

    // Tags
    'tags.index' => [
        'path' => '/tags/',
        'controller' => 'POS\TagsController@index',
        'methods' => ['GET'],
    ],
    'tags.show' => [
        'path' => '/tags/{slug}/',
        'controller' => 'POS\TagsController@show',
        'methods' => ['GET'],
        'constraints' => ['slug' => '[a-z0-9-]+'],
    ],
    'tags.products' => [
        'path' => '/tags/{slug}/products/',
        'controller' => 'POS\TagsController@products',
        'methods' => ['GET'],
        'constraints' => ['slug' => '[a-z0-9-]+'],
    ],

    // Search and Filters
    'pos.search' => [
        'path' => '/search/',
        'controller' => 'POS\SearchController@index',
        'methods' => ['GET'],
    ],
    'products.filter' => [
        'path' => '/products/filter/',
        'controller' => 'POS\ProductsController@filter',
        'methods' => ['GET'],
    ],

    // Collections
    'collections.index' => [
        'path' => '/collections/',
        'controller' => 'POS\CollectionsController@index',
        'methods' => ['GET'],
    ],
    'collections.show' => [
        'path' => '/collections/{id}/',
        'controller' => 'POS\CollectionsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],

    // Campaigns and Promotions
    'campaigns.index' => [
        'path' => '/campaigns/',
        'controller' => 'POS\CampaignsController@index',
        'methods' => ['GET'],
    ],
    'campaigns.show' => [
        'path' => '/campaigns/{id}/',
        'controller' => 'POS\CampaignsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'promotions.index' => [
        'path' => '/promotions/',
        'controller' => 'POS\PromotionsController@index',
        'methods' => ['GET'],
    ],
    'promotions.show' => [
        'path' => '/promotions/{id}/',
        'controller' => 'POS\PromotionsController@show',
        'methods' => ['GET'],
        'constraints' => ['id' => '[a-z0-9-]+'],
    ],
    'deals.index' => [
        'path' => '/deals/',
        'controller' => 'POS\DealsController@index',
        'methods' => ['GET'],
    ],
    'seasonal.index' => [
        'path' => '/seasonal/',
        'controller' => 'POS\SeasonalController@index',
        'methods' => ['GET'],
    ],

    // User Account
    'account.index' => [
        'path' => '/account/',
        'controller' => 'POS\AccountController@index',
        'methods' => ['GET'],
    ],
    'account.profile' => [
        'path' => '/account/profile/',
        'controller' => 'POS\AccountController@profile',
        'methods' => ['GET', 'POST'],
    ],
    'account.orders' => [
        'path' => '/account/orders/',
        'controller' => 'POS\AccountController@orders',
        'methods' => ['GET'],
    ],
    'account.wishlist' => [
        'path' => '/account/wishlist/',
        'controller' => 'POS\AccountController@wishlist',
        'methods' => ['GET'],
    ],
    'account.addresses' => [
        'path' => '/account/addresses/',
        'controller' => 'POS\AccountController@addresses',
        'methods' => ['GET', 'POST'],
    ],
    'account.settings' => [
        'path' => '/account/settings/',
        'controller' => 'POS\AccountController@settings',
        'methods' => ['GET', 'POST'],
    ],
    'account.preferences' => [
        'path' => '/account/preferences/',
        'controller' => 'POS\AccountController@preferences',
        'methods' => ['GET', 'POST'],
    ],

    // Support
    'help.index' => [
        'path' => '/help/',
        'controller' => 'POS\HelpController@index',
        'methods' => ['GET'],
    ],
    'help.faq' => [
        'path' => '/help/faq/',
        'controller' => 'POS\HelpController@faq',
        'methods' => ['GET'],
    ],
    'help.shipping' => [
        'path' => '/help/shipping/',
        'controller' => 'POS\HelpController@shipping',
        'methods' => ['GET'],
    ],
    'help.returns' => [
        'path' => '/help/returns/',
        'controller' => 'POS\HelpController@returns',
        'methods' => ['GET'],
    ],
    'help.size_guide' => [
        'path' => '/help/size-guide/',
        'controller' => 'POS\HelpController@sizeGuide',
        'methods' => ['GET'],
    ],
    'contact.form' => [
        'path' => '/contact/',
        'controller' => 'POS\ContactController@form',
        'methods' => ['GET', 'POST'],
    ],

    // Admin (if applicable)
    'admin.index' => [
        'path' => '/admin/',
        'controller' => 'POS\AdminController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
    'admin.products' => [
        'path' => '/admin/products/',
        'controller' => 'POS\Admin\ProductsController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
    'admin.inventory' => [
        'path' => '/admin/inventory/',
        'controller' => 'POS\Admin\InventoryController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
    'admin.orders' => [
        'path' => '/admin/orders/',
        'controller' => 'POS\Admin\OrdersController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
    'admin.reports' => [
        'path' => '/admin/reports/',
        'controller' => 'POS\Admin\ReportsController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
    'admin.colors' => [
        'path' => '/admin/colors/',
        'controller' => 'POS\Admin\ColorsController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
    'admin.tags' => [
        'path' => '/admin/tags/',
        'controller' => 'POS\Admin\TagsController@index',
        'methods' => ['GET'],
        'middleware' => ['admin'],
    ],
];
