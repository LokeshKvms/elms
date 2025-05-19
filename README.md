# 🗂️ Employee Leave Management System
A web-based leave management system built with PHP, MySQL, Bootstrap 5, and jQuery. 
It enables employees to apply for leave and admins to approve or reject requests, while tracking leave balances and generating reports.

## 🚀 Features
- 📝 Employees can apply for different types of leave  
- 🔐 Secure login system for employees and administrators  
- ✅ Admin approval and rejection of leave requests  
- 📊 Dashboard with leave statistics and summaries  
- 📅 Leave calendar with real-time balance updates  
- 🔔 Toast notifications using SweetAlert2  
- 📁 Clean and modular codebase for easy maintenance

## ⚙️ Setup and Installation
Follow these steps to set up the project locally

### 1. Clone the repository and move it to htdocs folders
git clone https://github.com/LokeshKvms/elms
cd elms

### 2. Install dependencies
composer install

### 3. Setup database
Create a database named employee_leave and import the .sql file to dump the data.

### 4. Configure enviroment varaibles
In the project root, create a file named .env
Add your credentials of the mysql database and the email credentials (email address and app password) in the following format :

DB_HOST=localhost

DB_NAME=employee_leave

DB_USER=root

DB_PASSWORD=

SMTP_USER=

SMTP_PASS=

### 5. Start the development server
php -S localhost:8080
Then open your browser and go to http://localhost:8080/elms
