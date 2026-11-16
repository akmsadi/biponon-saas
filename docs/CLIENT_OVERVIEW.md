# Biponon: What We Built

As of 16 November 2026

Biponon is a finished, live web app that keeps track of every item your business buys, stores, moves and sells, across all your warehouses and shops. It runs in any browser, on a computer or a phone, with nothing to install.

## What Biponon is

Biponon replaces the spreadsheets, notebooks and phone calls that usually tell a business what it has in stock. Every time stock arrives, moves between locations, goes out to a customer or comes back, someone records it in Biponon. The app then always knows how many units of each item sit in each location.

It was built with clothing and retail businesses in mind: products come in sizes and colours, and each size and colour combination is tracked on its own. Several businesses can use the same Biponon, each seeing only its own data.

## What you can do with it

| Area | What it gives you |
| --- | --- |
| Dashboard | Units in stock, items running low, stock in and out over the last 30 days, and the latest activity, on one screen. |
| Catalog | Your products with their sizes and colours. Each combination gets its own code (SKU), price, barcode and a "reorder level" that warns you when stock runs low. |
| Warehouses | Every place you keep stock: warehouses, depots, shops. Close one and its history stays. |
| Stock overview | How many units of each item sit in each location, with filters for low and out-of-stock items. |
| Serial numbers | Optional tracking of individual units, such as high-value garments, from arrival to the customer and back. |
| Stock movements | A complete history of every change, searchable and downloadable as a spreadsheet. |
| Day-to-day operations | Receive stock, transfer between locations, dispatch to customers and take returns. |
| Purchasing | Suppliers and purchase orders. Receive an order in parts, split it across locations and record what each item cost. |
| Sales | Customers and sales orders. Dispatch from one or several locations, take returns, and see a warning when stock is short. |
| Reports | Sales by product or customer, stock value, purchases by supplier, and slow-moving stock. Each one downloads as a spreadsheet. |

Every screen shows dates in your business's own time zone and money in your chosen currency (Bangladeshi taka by default).

## Who can do what

Each person in your business gets one of three roles, so staff can do their daily work without seeing costs or changing settings.

| | Staff | Manager | Admin |
| --- | --- | --- | --- |
| See stock, orders, movements and sales reports | Yes | Yes | Yes |
| Receive, transfer, dispatch and return stock | Yes | Yes | Yes |
| Draft purchase and sales orders | Yes | Yes | Yes |
| Confirm, cancel or close orders | No | Yes | Yes |
| Edit products, warehouses, suppliers and customers | No | Yes | Yes |
| See purchase costs and stock value | No | Yes | Yes |
| Invite people, change roles, edit company settings | No | No | Yes |

**Joining is by invitation only.** An admin enters a colleague's email address, and Biponon creates a personal link that lets them join. Nobody can sign up on their own.

**One login, several companies.** A person can belong to more than one business and switch between them from the menu. Each business's data is kept completely separate.

**Getting a business set up.** Visitors to the Biponon home page can ask for access with a short form, in English or Bangla. We then create the business in Biponon and invite its first admin.

## Try it yourself

You can explore a sample business, "Demo Fashion Ltd", with about five months of made-up history: products, three warehouses, orders in every stage, and reports full of data.

1. Open [saas-backend.mypersonalwork.com/login](https://saas-backend.mypersonalwork.com/login).
2. Under the login form, choose **Admin**, **Manager** or **Staff**. The form fills itself in.
3. Click **Log in**. Try each role to see what it can and cannot do.

While you are in the demo, a yellow bar at the bottom of each page explains how that page was built. Feel free to change anything: the demo resets itself every night, and it never touches real businesses' data.

## Safety and reliability

- **Your data stays yours.** Each business sees only its own products, stock, orders and people. The system checks this on every page and every action.
- **Stock numbers can't drift.** Each change is recorded as a history entry, and stock is updated in the same step. Two people working at once can't push stock below zero.
- **Nightly backups.** Every night at 2:00 Dhaka time the whole database is backed up and locked with a password. A copy goes to a separate Dropbox account, so one problem can't destroy both.
- **Secure sign-in.** Connections are encrypted (HTTPS). People can add two-step verification or passkeys to their account. Repeated wrong passwords are slowed down.
- **Tested before release.** More than 300 automated checks run before every update goes live.

## Getting started and what could come next

To start, request access on the Biponon home page, or contact us directly. Once your business is created, your admin receives an invitation link, sets a password, and can add products, warehouses and the team the same day.

Possible next steps, depending on what your business needs:

- Invitation emails sent automatically (today the admin copies the link and shares it)
- Taxes, discounts and shipping costs on orders
- Invoices and delivery notes to print
- Barcode scanning from a phone camera
