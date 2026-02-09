# WC Multi-Fabric Master Tool

A high-performance WooCommerce utility designed for massive catalogs (4,500+ products). This plugin allows you to safely inject new fabric attributes and generate thousands of variations in background batches using the Action Scheduler.

## 🚀 Key Features
- **Batch Processing:** Uses Action Scheduler to process 10k+ products without server timeouts.
- **Dual Pricing:** Set Regular and Sale prices instantly during variation generation.
- **Deep Cleanup:** Completely removes variations and force-unlinks attributes from parent products.
- **Auto-Threshold:** Automatically increases the WooCommerce AJAX limit to 1,000 to keep the front-end fast.
- **Live Stats:** Monitor the total number of variations in your database from the tool's dashboard.

## 🛠 Installation
1. Download this repository as a ZIP.
2. Upload to your WordPress site via **Plugins > Add New**.
3. Activate the plugin.
4. Ensure you have **WooCommerce** active.

## 📖 How to Use
1. **Prepare:** Add your new term (e.g., "Linen") under **Products > Attributes > Fabric**.
2. **Inject:** Go to **Tools > Variation Injector**.
3. **Configure:** Enter the fabric slug, regular price, and optional sale price.
4. **Run:** Click "Generate" and monitor progress in **Tools > Scheduled Actions**.

## ⚠️ Safety Disclaimer
**IMPORTANT:** This tool performs bulk database operations. 
- **Always** perform a full database backup before running an injection or cleanup.
- Test on a staging/development site first.
- The author is not responsible for any data loss or site downtime.

## ⚖️ License
This project is licensed under the MIT License - see the LICENSE file for details.
