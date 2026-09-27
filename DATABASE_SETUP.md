# K Supermarket - Database Setup Guide

## 📌 Quick Start

Your complete SQL database file is ready! Here's how to set it up:

### Files Included:
- **database.sql** - Complete database schema + sample data (40+ products, 10 categories, 6 suppliers, sample orders)
- **discounts.sql** - Offers/discounts table and order discount audit columns (run after `database.sql`)
- **install.php** - Visual setup guide and status checker
- **setup-db.php** - Password hash generator for test accounts

---

## 🚀 Setup Method 1: phpMyAdmin (Easiest)

1. **Start XAMPP**
   - Start Apache and MySQL services

2. **Open phpMyAdmin**
   - Go to: http://localhost/phpmyadmin

3. **Import Database**
   - Click the **Import** tab at top
   - Click **Choose File**
   - Select `database.sql` from your project folder
   - Click **Import** button
   - Wait for success message ✓

4. **Update Passwords**
   - Go to: http://localhost/k-supermarket/setup-db.php
   - Copy the SQL UPDATE statement shown
   - Return to phpMyAdmin
   - Click the **SQL** tab
   - Paste the UPDATE statement
   - Click **Go** to execute
   - ✓ Done!

5. **Enable Offers & Discounts**
   - In phpMyAdmin, select the `k_supermarket` database and import `discounts.sql`.
   - The admin can then manage offers at `admin/offers.php`.

---

## 🖥️ Setup Method 2: Command Line

1. **Open Command Prompt** (Windows) or **Terminal** (Mac/Linux)

2. **Navigate to project folder**
   ```bash
   cd c:\xampp\htdocs\k-supermarket
   ```

3. **Run SQL import**
   ```bash
   mysql -u root -proot < database.sql
   ```

   Then run the offers migration:
   ```bash
   mysql -u root -proot < discounts.sql
   ```

4. **Update passwords**
   - Visit: http://localhost/k-supermarket/setup-db.php
   - Copy and paste the UPDATE statement in phpMyAdmin SQL tab

---

## 📊 What Gets Created

### **10 Product Categories**
- Vegetables
- Fruits
- Dairy & Eggs
- Meat & Poultry
- Grains & Cereals
- Spices & Condiments
- Beverages
- Bakery
- Frozen Foods
- Snacks & Sweets

### **Database Tables**
| Table | Records | Purpose |
|-------|---------|---------|
| users | 6 | Admin, Staff, Customers |
| categories | 10 | Product types |
| suppliers | 6 | Supplier information |
| products | 40+ | All products with pricing |
| orders | 4 | Sample customer orders |
| order_items | 15+ | Order line items |
| discounts | — | Scheduled offers and promo codes (created by `discounts.sql`) |

### **Sample Users**

| Username | Password | Role | Email |
|----------|----------|------|-------|
| admin | password123 | Admin | admin@kmarketplace.com |
| staff1 | password123 | Staff | staff1@kmarketplace.com |
| staff2 | password123 | Staff | staff2@kmarketplace.com |
| customer1 | password123 | Customer | customer1@kmarketplace.com |
| customer2 | password123 | Customer | customer2@kmarketplace.com |
| customer3 | password123 | Customer | customer3@kmarketplace.com |

---

## 🔍 Verify Installation

1. Go to: http://localhost/k-supermarket/install.php
   - This page shows installation status
   - Confirms database connection

2. Try logging in: http://localhost/k-supermarket/login.php
   - Use admin / password123
   - Should see admin dashboard

---

## 🎯 What Each Role Can Do

### **Admin Dashboard** (admin/dashboard.php)
- ✅ View all products, categories, suppliers
- ✅ Add, edit, delete products
- ✅ Manage inventory
- ✅ View all customer orders
- ✅ Access admin reports
- ✅ Create, edit, schedule, and deactivate offers and promo codes

### **Staff Dashboard** (staff/dashboard.php)
- ✅ View all orders
- ✅ Update order status (pending → processing → completed)
- ✅ View inventory stock levels
- ✅ Low stock alerts
- ✅ Generate reports

### **Customer Dashboard** (customer/dashboard.php)
- ✅ Browse products
- ✅ View my orders
- ✅ Track order status
- ✅ View profile
- ✅ Add items to cart
- ✅ See active offers and apply promo codes at checkout

---

## 📋 Sample Products Included

### Vegetables (6 products)
- Tomatoes: Rs. 80/kg
- Carrots: Rs. 60/kg
- Potatoes: Rs. 40/kg
- Onions: Rs. 50/kg
- Bell Peppers: Rs. 120/piece
- Cabbage: Rs. 45/kg

### Fruits (5 products)
- Bananas: Rs. 100/kg
- Apples: Rs. 200/kg
- Oranges: Rs. 120/kg
- Mangoes: Rs. 180/kg
- Papaya: Rs. 90/piece

### Dairy & Eggs (5 products)
- Milk (1L): Rs. 110
- Cheddar Cheese: Rs. 450
- Eggs (Dozen): Rs. 280
- Butter (500g): Rs. 350
- Yogurt (500ml): Rs. 150

### And More...
- Meat & Poultry (4 products)
- Grains & Cereals (4 products)
- Spices & Condiments (5 products)
- Beverages (5 products)
- Bakery (3 products)
- Frozen Foods (3 products)
- Snacks & Sweets (4 products)

---

## 🛠️ Troubleshooting

### "Access Denied" error
- Check MySQL is running in XAMPP
- Verify username/password in db.php (should be root/root)

### "Database already exists"
- You already imported the database
- To reset: DROP DATABASE k_supermarket; then re-import

### "Table doesn't exist" error
- Make sure you completed the import
- Check in phpMyAdmin that tables exist

### Products not showing
- Verify products table has data
- Check category_id references match

---

## 🔐 Security Notes

⚠️ **For Production:**
- Change default passwords
- Use strong passwords
- Add SSL/HTTPS
- Update database credentials
- Enable proper access controls

✅ **This database is for development/testing only**

---

## 📁 File Structure

```
k-supermarket/
├── database.sql           ← Complete SQL file (RUN THIS FIRST)
├── install.php            ← Setup guide & status checker
├── setup-db.php          ← Password generator
├── login.php             ← Login page
├── register.php          ← Registration
├── index.php             ← Home page
├── db.php                ← Database connection
├── logout.php            ← Logout
├── admin/                ← Admin dashboard files
├── staff/                ← Staff dashboard files
└── customer/             ← Customer dashboard files
```

---

## ✅ Setup Checklist

- [ ] Download database.sql
- [ ] Start XAMPP (Apache + MySQL)
- [ ] Import database.sql via phpMyAdmin
- [ ] Visit setup-db.php
- [ ] Update password hashes
- [ ] Test login with admin/password123
- [ ] Verify admin dashboard loads
- [ ] Test staff login (staff1/password123)
- [ ] Test customer login (customer1/password123)
- [ ] You're done! 🎉

---

## 💡 Tips

1. **Test the application** with all three roles
2. **Create new products** through admin panel
3. **Place test orders** from customer account
4. **Update order statuses** from staff panel
5. **Monitor stock levels** in inventory section

---

## 📞 Support

If you encounter issues:
1. Check MySQL/Apache are running
2. Verify database.sql file is in project root
3. Check db.php connection settings
4. Review error messages in browser console

---

**Database setup by:** K Supermarket Development
**Created:** 2026-07-23
**Version:** 1.0
