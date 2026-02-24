# Quick Setup Guide - Shop & Category Lookbook

## 🎯 What You're Seeing

Your shop page is showing the products correctly, but it needs lookbook images to display at the top. Here's how to add them:

## 📸 How to Add Lookbook Images

### For the SHOP Page (Main /shop/ page):

The shop page automatically pulls the **first lookbook image** from each product category. So to fix your shop page:

1. Go to **Products → Categories** in WordPress
2. For each category (Necklaces, Rings, Earrings, etc.):
   - Click **Edit**
   - Scroll to **"Category Lookbook Images"**
   - Upload at least **Lookbook Image 1** (this shows on the shop page)
   - Optionally add images 2-6 (these show on the category page)
   - Click **Update**

### For CATEGORY Pages (like /product-category/necklaces/):

Individual category pages will show ALL the lookbook images you upload (up to 6) followed by the products in that category.

## 🔧 Current Setup

Based on your screenshot, you have:
- ✅ Products created and assigned to categories
- ✅ Theme installed
- ⚠️ **Missing**: Lookbook images uploaded to categories

## 📝 Step-by-Step Right Now

1. **WordPress Admin** → **Products** → **Categories**
2. Find **"Necklaces"** → Click **Edit**
3. Scroll down to **"Category Lookbook Images"** section
4. Click **"Upload Image"** for **Lookbook Image 1**
5. Choose a lifestyle photo of someone wearing a necklace
6. Click **Update** at the bottom
7. Visit your shop page - you'll see the image with a "NECKLACES" button

Repeat for each category (Rings, Earrings, Bracelets, etc.)

## 🖼️ Image Requirements

- **Aspect Ratio**: Portrait (3:4 ratio works best)
- **Size**: At least 900px × 1200px
- **Content**: Lifestyle shots showing jewelry being worn
- **Format**: JPG or PNG

## 💡 Pro Tip

Upload multiple images per category:
- **Image 1**: Shows on shop page + category page
- **Images 2-6**: Only show on category page

This creates a beautiful vertical lookbook gallery on each category page!

## ❓ Still Not Showing?

1. Make sure you clicked **"Update"** after uploading
2. Hard refresh your browser (Ctrl+Shift+R or Cmd+Shift+R)
3. Check WordPress Admin → Products → Categories to verify images saved
4. Clear site cache if you're using a caching plugin

---

**Need Help?** The lookbook images are stored as custom fields on your product categories. If the upload interface isn't showing, make sure the `category-lookbook-fields.php` file is in your theme folder and functions.php is loading it.
