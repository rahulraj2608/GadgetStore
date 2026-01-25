# 🛒 E-Commerce Platform with Admin Panel

A full-stack **E-Commerce Platform** featuring a robust **Admin Panel**, secure authentication, API key management, rate limiting, and database-backed logging. This project is designed with real-world backend engineering practices, security, and scalability in mind.

---

## 🚀 Features Overview

### 👥 User Roles
- **Admin**: Full system control
- **Customer**: Shopping, ordering, and reviews

---

## 🔐 Authentication & Security
- Email & password authentication
- **Google OAuth** integration
- Secure password hashing
- Email verification using token-based validation
- Role-based access control (Admin / Customer)

---

## 🛍️ Customer Features
- Browse products by category and brand
- View product details with images
- Add products to cart
- Place orders and checkout
- View order history
- Submit product reviews & ratings

---

## 🧑‍💼 Admin Panel Features
- Product, brand, category & supplier management
- Upload and manage product images
- Manage customer orders and update order status
- Generate invoices
- Create and manage discounts
- Generate and manage API keys
- View system activity logs

---

## 🔑 API Key Management
### Overview
The API allows access to product data while enforcing **3 layers of security**:
1. **Throttling (Tier 1)** – Token bucket for 20-60 req/min
2. **Rate Limiting (Tier 2)** – Max 60 req/min
3. **IP Ban (Tier 3)** – Permanent ban for >120 req/min

### Workflow
- **Step 0:** Check if IP is blacklisted
- **Step 1:** Validate API key (hashed) and active status
- **Step 2:** Track requests in a per-minute window
- **Step 3:** Apply token bucket throttling
- **Step 4:** Persist request and token stats
- **Step 5:** Fetch paginated product data with joins on brand, category, and supplier
- **Step 6:** Log significant events using `write_log()`
- **Step 7:** Handle errors with proper HTTP codes

### Security Considerations
- Prepared statements prevent SQL injection
- SHA-256 hashing for API keys
- Tiered approach prevents abuse and ensures fair usage
- Database logging ensures traceability


---

## 🧾 Activity Logging
- Database-backed logging system
- Logs include:
  - Event type
  - User reference (if applicable)
  - Timestamp
- Used for auditing, debugging, and security monitoring

---

## 🗄️ Database Design
- MySQL (InnoDB engine)
- UTF-8 (`utf8mb4`) encoding
- Foreign key constraints for referential integrity
- Normalized schema (3NF / BCNF-oriented)

### Core Tables
```
customer
product
brand
category
sub_category
supplier
product_image
cart
order
order_item
payment
shipment
review
discount
verification
activity_log
api_key
```

---

## 🧠 Technology Stack

### Backend
- PHP (PDO)
- MySQL

### Frontend
- HTML5
- CSS3
- Bootstrap
- JavaScript

### Authentication
- Google OAuth

---

## ⚙️ Setup Instructions

### 1️⃣ Clone the repository
```bash
git clone https://github.com/your-username/your-repo-name.git
```

### 2️⃣ Configure Database
- Update database credentials in `setup.php`

### 3️⃣ Run Setup Script
- Open the following URL in your browser:
```
http://localhost/your-project/setup.php
```

This will:
- Create the database and tables
- Insert sample data
- Create default admin and test customer

---

## 🔑 Default Login Credentials

**Admin**  
📧 admin@gadgetstore.com  
🔑 admin123

**Customer**  
📧 customer@example.com  
🔑 customer123

---

## 🔒 Security Practices
- Prepared statements to prevent SQL injection
- Password hashing using `password_hash()`
- API keys never stored in plaintext
- OAuth handled securely via Google

---

## 📈 Future Enhancements
- Advanced search and filtering
- Wishlist functionality
- Email notifications
- Admin analytics dashboard
- API documentation (Swagger)
- Caching for high-traffic endpoints

---

## 📄 License
This project is for educational and portfolio purposes.

---

## ✨ Author
**Rahul Raj Dey**  
Backend / Full-Stack Developer

---

## ⭐ Final Note
This project demonstrates **real-world backend engineering concepts**, including security, system design, and scalability, making it suitable for **resume, portfolio, and interviews**.

