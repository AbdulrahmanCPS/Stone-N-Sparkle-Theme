# Stone & Sparkle Theme - Product Category Lookbook Update

## What's New

This update adds a beautiful lookbook-style layout to your product category pages (like the Necklaces category). Each category can now display up to 6 lifestyle/lookbook images at the top of the page, followed by your products in a clean 3-column grid.

## Changes Made

### 1. Visual Updates
- ✅ **Lookbook Grid**: Categories now support up to 6 lookbook images displayed vertically
- ✅ **Product Grid**: Changed from 4 columns to 3 columns for better product visibility
- ✅ **Product Cards**: Refined styling with cleaner white backgrounds and subtle shadows
- ✅ **Consistent Buttons**: All lookbook buttons now have identical width and spacing

### 2. New Files Added
- `woocommerce/taxonomy-product_cat.php` - Custom template for product category pages
- `category-lookbook-fields.php` - Admin interface for uploading lookbook images

### 3. Updated Files
- `functions.php` - Added lookbook functionality and changed grid to 3 columns
- `assets/css/main.css` - New styles for category lookbook grid and refined product cards

## How to Add Lookbook Images to Categories

### Step 1: Navigate to Product Categories
1. Go to **WordPress Admin** → **Products** → **Categories**
2. Click **Edit** on the category you want to add lookbook images to (e.g., "Necklaces")

### Step 2: Upload Lookbook Images
1. Scroll down to the **"Category Lookbook Images"** section
2. You'll see 6 upload slots labeled "Lookbook Image 1" through "Lookbook Image 6"
3. Click **"Upload Image"** for each slot you want to use
4. Select images from your media library or upload new ones
5. Click **"Update"** at the bottom of the page to save

### Step 3: View Your Category Page
1. Visit your category page (e.g., `/product-category/necklaces/`)
2. Your lookbook images will display in a vertical stack at the top
3. Products will appear below in a 3-column grid

## Image Recommendations

For best results with lookbook images:
- **Aspect Ratio**: 3:4 (portrait orientation)
- **Recommended Size**: 900px × 1200px minimum
- **File Format**: JPG or PNG
- **Style**: Lifestyle/editorial shots that showcase the jewelry being worn

## Product Card Styling

Products now display with:
- Clean white background cards
- 3:4 aspect ratio product images
- Subtle shadows that lift on hover
- Centered product titles and prices
- 3-column responsive grid (2 columns on tablets, 1 column on mobile)

## Responsive Design

The lookbook grid is fully responsive:
- **Desktop**: Single column, centered, max 450px wide
- **Tablet**: Single column, centered, max 380px wide
- **Mobile**: Full width with padding, single column

## Troubleshooting

### Images not showing?
- Make sure you clicked **"Update"** after uploading images
- Clear your browser cache (Ctrl+Shift+R or Cmd+Shift+R)
- Check that the images were uploaded successfully in the media library

### Old styles showing?
- Clear browser cache with hard refresh
- If using a caching plugin, clear the site cache
- Check that the updated CSS file is loading

### Lookbook fields not appearing?
- Make sure `category-lookbook-fields.php` is in your theme folder
- Verify `functions.php` has the `require_once` line for the lookbook fields

## Support

If you need help or want to customize further:
1. Check that all files were uploaded correctly
2. Clear all caches (browser + WordPress)
3. Make sure WooCommerce is active and product categories exist

---

**Version**: 0.2.1  
**Last Updated**: February 2026
