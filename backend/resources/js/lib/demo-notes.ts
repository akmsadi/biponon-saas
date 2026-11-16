/**
 * What the demo panel says, shown at the bottom of every page while demo mode is on.
 * Paths are relative to the repository's backend/ folder and link to GitHub.
 */

export const DEMO_REPOSITORY_URL = 'https://github.com/akmsadi/biponon-saas';

export const DEMO_STACK: { name: string; role: string }[] = [
    {
        name: 'Laravel 13',
        role: 'PHP 8.3+ backend, routing, validation, queues',
    },
    {
        name: 'Inertia.js 3',
        role: 'server-driven single-page app, no separate API',
    },
    { name: 'React 19', role: 'UI, with the React Compiler' },
    { name: 'TypeScript', role: 'typed pages, props and routes' },
    { name: 'Tailwind CSS 4', role: 'styling, light and dark themes' },
    {
        name: 'Radix UI / shadcn',
        role: 'accessible dialogs, menus and selects',
    },
    { name: 'Laravel Fortify', role: 'login, 2FA, passkeys, password reset' },
    {
        name: 'Wayfinder',
        role: 'type-safe links and forms generated from Laravel routes',
    },
    { name: 'Recharts', role: 'dashboard chart' },
    { name: 'MySQL', role: 'production database (SQLite in tests)' },
    { name: 'PHPUnit 12', role: 'about 300 feature tests' },
    {
        name: 'GitHub Actions',
        role: 'tests and a ready-to-deploy build on every push',
    },
];

export const DEMO_ARCHITECTURE: string[] = [
    'Multi-tenant: every query is scoped to the current organization, and records from another organization return 404.',
    'Three roles (admin, manager, member) enforced by route middleware; pages receive a permissions object so they only show buttons the user may use.',
    'Every stock change goes through one action class that locks the balance row inside a database transaction, so totals stay correct when people work at the same time.',
    'Deployed to shared cPanel hosting: GitHub Actions runs the tests and publishes a production branch; one cron job runs the queue and nightly database backups.',
];

type PageNote = {
    title: string;
    points: string[];
    files: string[];
};

/** Keyed by Inertia page component name. Pages without a note show only the stack. */
export const DEMO_PAGE_NOTES: Record<string, PageNote> = {
    welcome: {
        title: 'Landing page',
        points: [
            "The English / Bangla switch uses React's useSyncExternalStore: the server always renders English and the browser switches after hydration, so the two never disagree.",
            'The dashboard picture is HTML and Tailwind, not a screenshot. It follows light and dark mode and shows Bangla digits through Intl number formatting.',
            'The Bangla font is bundled at build time but not preloaded, so browsers only download it when Bangla text appears.',
        ],
        files: [
            'resources/js/pages/welcome.tsx',
            'resources/js/hooks/use-landing-language.ts',
            'resources/js/lib/landing-copy.ts',
        ],
    },
    dashboard: {
        title: 'Dashboard',
        points: [
            "Stock flow is totalled per hour in SQL (written to run on both MySQL and SQLite), then grouped into days in the organization's timezone in the browser, so day boundaries are right wherever the business is.",
            'The Recharts chart has a legend, tooltips and a table view; its colors are CSS variables that switch with dark mode.',
            'The summary tiles share one query class with the Stock overview page.',
        ],
        files: [
            'app/Http/Controllers/DashboardController.php',
            'app/Actions/Inventory/SummarizeStock.php',
            'resources/js/components/stock-flow-chart.tsx',
        ],
    },
    'inventory/stock': {
        title: 'Stock overview',
        points: [
            'Quantities live in a stock_balances table (one row per variant and warehouse) that only the stock-movement action writes to.',
            'Filters live in the URL query string, so a filtered view can be bookmarked or shared.',
            "Low stock compares each row with the variant's own reorder level in SQL, then the result is paginated on the server.",
        ],
        files: [
            'app/Http/Controllers/Inventory/StockOverviewController.php',
            'app/Concerns/ScopesToOrganization.php',
        ],
    },
    'inventory/serials': {
        title: 'Serial numbers',
        points: [
            "Serial numbers are optional and linked to stock movements through a pivot table, so each serial's full history can be traced.",
            "History opens in a dialog loaded with Inertia's useHttp JSON request, without leaving the page.",
            'The page is read-only: status and location change only through stock operations.',
        ],
        files: [
            'app/Http/Controllers/Inventory/SerialNumberController.php',
            'resources/js/pages/inventory/serials.tsx',
        ],
    },
    'inventory/movements': {
        title: 'Stock movements',
        points: [
            'The CSV export streams rows as it reads them, so large exports use little memory. It adds a byte-order mark for Excel and escapes values that spreadsheets would run as formulas.',
            "Date filters work in the organization's timezone.",
            'Unit costs are removed on the server for members, not just hidden in the browser.',
        ],
        files: [
            'app/Http/Controllers/Inventory/StockMovementController.php',
            'app/Concerns/StreamsCsv.php',
        ],
    },
    'operations/receive': {
        title: 'Receive stock',
        points: [
            'Every line goes through RecordStockMovement, which locks the balance row (SELECT … FOR UPDATE) inside a transaction.',
            'Up to 50 lines at once; optional serial numbers are checked for duplicates without regard to letter case.',
            'Validation lives in a form request and returns an error for each line.',
        ],
        files: [
            'app/Http/Controllers/Operations/ReceiveStockController.php',
            'app/Actions/Inventory/RecordStockMovement.php',
            'app/Http/Requests/Operations/ReceiveStockRequest.php',
        ],
    },
    'operations/transfer': {
        title: 'Transfer stock',
        points: [
            'A transfer takes stock out of one warehouse and into another in a single transaction.',
            'The quantity is checked against the source warehouse in validation and again under a row lock, so a balance can never go below zero.',
            'The form limits each line to what the source warehouse holds.',
        ],
        files: [
            'app/Http/Controllers/Operations/TransferStockController.php',
            'app/Http/Requests/Operations/TransferStockRequest.php',
            'resources/js/components/stock-line.tsx',
        ],
    },
    'purchasing/orders/receive': {
        title: 'Receive against a purchase order',
        points: [
            'Each order line can be split across several warehouses in one delivery.',
            'Receiving more than was ordered is blocked; the remaining quantity is checked again after the purchase order row is locked.',
            'The unit cost is copied onto the stock movement, which feeds the weighted-average stock value report.',
            'The order moves to partially received or received on its own.',
        ],
        files: [
            'app/Http/Controllers/Purchasing/PurchaseOrderReceiptController.php',
            'app/Http/Requests/Purchasing/ReceivePurchaseOrderRequest.php',
        ],
    },
    'sales/orders/dispatch': {
        title: 'Dispatch a sales order',
        points: [
            'Each line can ship from several warehouses, and each part is limited to the stock held there.',
            'Sending more than was ordered is blocked under a lock on the sales order row.',
            'Dispatched serial numbers are marked as sent and leave the warehouse.',
        ],
        files: [
            'app/Http/Controllers/Sales/SalesOrderDispatchController.php',
            'app/Http/Requests/Sales/DispatchSalesOrderRequest.php',
        ],
    },
    'sales/orders/return': {
        title: 'Return against a sales order',
        points: [
            'A return is linked to the original order line, and no more can come back than was sent.',
            'A returned serial number must have been dispatched on that same order line.',
            'These checks are a PHP trait shared with the plain Return stock page.',
        ],
        files: [
            'app/Http/Controllers/Sales/SalesOrderReturnController.php',
            'app/Concerns/ValidatesReturnedSerialNumbers.php',
        ],
    },
    'reports/stock-value': {
        title: 'Stock value report',
        points: [
            'Weighted-average cost per variant is calculated in SQL from costed deliveries. Money is handled in whole cents to avoid rounding errors.',
            'Only managers and admins can open it; route middleware enforces this.',
            'The CSV export uses the same filters as the screen.',
        ],
        files: [
            'app/Http/Controllers/Reports/StockValueController.php',
            'app/Actions/Inventory/AverageReceiptCosts.php',
        ],
    },
    'organization/members': {
        title: 'Members and invitations',
        points: [
            'Joining is by invitation only. Invitation tokens are stored as SHA-256 hashes, expire after 7 days, and the emails go through the queue.',
            "Admins can't change or remove themselves, so an organization always keeps an admin.",
            'Only admins can open the page, and the sidebar link is hidden from everyone else.',
        ],
        files: [
            'app/Http/Controllers/Organization/MemberController.php',
            'app/Http/Controllers/Organization/InvitationController.php',
            'app/Actions/Organizations/SendInvitation.php',
        ],
    },
    'admin/organizations': {
        title: 'Platform: organizations',
        points: [
            'Super-admins create, rename and suspend organizations from outside them and never see their inventory data.',
            'Super-admin rights are granted only from the command line (php artisan biponon:super-admin), never through the website.',
            'A new organization gets an admin invitation link straight away.',
        ],
        files: [
            'app/Http/Controllers/Admin/OrganizationController.php',
            'app/Console/Commands/SuperAdminCommand.php',
        ],
    },
};

export function demoSourceUrl(path: string): string {
    return `${DEMO_REPOSITORY_URL}/blob/main/backend/${path}`;
}
