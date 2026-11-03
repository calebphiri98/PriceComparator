AGRICULTURAL MARKET PRICE TRACKER
==================================
Case of Lunzu Farmers – Blantyre, Malawi
Student Project (BIS/22/SS/020)

HOW TO RUN THIS PROJECT (No XAMPP / WAMP needed)

1. Make sure you have PHP installed on your computer.
   - Download from: https://windows.php.net/download/
   - Extract to C:\php and add C:\php to your system PATH.

2. Open the folder "agricultural-market-price-tracker" in VS Code.

3. Open the Terminal in VS Code (Ctrl + `)

4. Type this command and press Enter:

   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

   Copy the generated key, then set it and start the server in the same terminal:

   $env:ADMIN_SETUP_KEY = "paste-the-generated-key-here"
   php -S localhost:8000

5. Open your browser and go to:

   http://localhost:8000

On the login page, select "Initial admin setup" and enter the setup key to create the first admin. Admin setup closes after the first admin is created. Keep the setup key private.

FEATURES INCLUDED
- Home / Landing page
- Market Prices (dated FAO FPMA observations sourced from IFPRI Malawi and FEWS NET, plus local listings and buyer offers)
- Public dated bar-chart report with crop and date filters
- Farmer Gross Margin Calculator (available from the hamburger menu)
- Contract Farming (with Apply)
- Outside Markets (buyers)
- Trending Crops
- Role-based accounts: Farmers use the gross margin calculator; Buyers post offers; Admins manage prices, contracts, and accounts
- Public registration is limited to Farmer and Buyer; existing Extension Officer accounts are migrated to Admin
- Admins are created through the protected one-time setup or by an existing admin
- SQLite database (automatic)

Online market-price entries are dated observations, not live quotes or Malawi government prices. The market-prices page links each online source.

COLOR THEME
Apple green / fresh green as requested.

When you need changes later, just tell me the specific file
and I will give you only the updated code for that file.
