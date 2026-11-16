import type { LandingLanguage } from '@/hooks/use-landing-language';

export type LandingFeatureKey =
    | 'stock'
    | 'serials'
    | 'purchasing'
    | 'sales'
    | 'reports'
    | 'roles';

type LandingCopy = {
    meta: { title: string; description: string };
    nav: {
        features: string;
        howItWorks: string;
        logIn: string;
        dashboard: string;
        requestAccess: string;
        language: string;
    };
    hero: {
        eyebrow: string;
        title: string;
        subtitle: string;
        note: string;
    };
    preview: {
        unitsInStock: string;
        lowStock: string;
        warehouses: string;
        lastSevenDays: string;
        stockIn: string;
        stockOut: string;
        recentMovements: string;
        received: string;
        dispatched: string;
        transferred: string;
        units: string;
    };
    features: {
        title: string;
        subtitle: string;
        items: Record<LandingFeatureKey, { title: string; body: string }>;
    };
    steps: {
        title: string;
        items: { title: string; body: string }[];
    };
    requestAccess: { title: string; body: string };
    footer: { rights: string };
};

/**
 * Every piece of text on the landing page, in English and Bangla.
 */
export const landingCopy: Record<LandingLanguage, LandingCopy> = {
    en: {
        meta: {
            title: 'Inventory management for growing businesses',
            description:
                'Biponon keeps stock, purchasing and sales in one place, across every warehouse, for your whole team.',
        },
        nav: {
            features: 'Features',
            howItWorks: 'How it works',
            logIn: 'Log in',
            dashboard: 'Dashboard',
            requestAccess: 'Request access',
            language: 'Language',
        },
        hero: {
            eyebrow: 'Inventory management for growing businesses',
            title: 'Know what you have, where it is, and where it went.',
            subtitle:
                'Biponon keeps stock, purchasing and sales in one place, across every warehouse, for your whole team.',
            note: 'Invitation only. We set up your organization for you.',
        },
        preview: {
            unitsInStock: 'Units in stock',
            lowStock: 'Low stock',
            warehouses: 'Warehouses',
            lastSevenDays: 'Last 7 days',
            stockIn: 'In',
            stockOut: 'Out',
            recentMovements: 'Recent movements',
            received: 'Received',
            dispatched: 'Dispatched',
            transferred: 'Transferred',
            units: 'units',
        },
        features: {
            title: 'Everything your stock touches, in one place',
            subtitle: "From the supplier's truck to the customer's door.",
            items: {
                stock: {
                    title: 'Stock across warehouses',
                    body: 'See live quantities for every size and color in every warehouse, with a low-stock level set per item.',
                },
                serials: {
                    title: 'Serial numbers',
                    body: "Follow individual items by serial number from the day they arrive to the day they're sold or returned.",
                },
                purchasing: {
                    title: 'Purchasing',
                    body: 'Raise purchase orders, receive deliveries in parts or split them across warehouses, and keep costs visible to managers only.',
                },
                sales: {
                    title: 'Sales orders',
                    body: 'Take sales orders, dispatch from any warehouse, and record returns against the original order.',
                },
                reports: {
                    title: 'Reports',
                    body: 'Stock value, sales, purchases by supplier and slow-moving stock, each one exportable to CSV.',
                },
                roles: {
                    title: 'Roles and permissions',
                    body: 'Admins, managers and staff each see and do exactly what they should, and every stock movement is recorded.',
                },
            },
        },
        steps: {
            title: 'Up and running in three steps',
            items: [
                {
                    title: 'We set up your organization',
                    body: "Request access and we'll create your organization and send you an admin invitation.",
                },
                {
                    title: 'Invite your team',
                    body: 'Add your warehouses and products, then invite managers and staff with the right role.',
                },
                {
                    title: 'Start receiving and selling',
                    body: 'Every delivery, transfer, sale and return updates your stock straight away.',
                },
            ],
        },
        requestAccess: {
            title: 'Ready to get organized?',
            body: "Tell us a little about your business. We'll set up your organization and send you an invitation.",
        },
        footer: { rights: 'All rights reserved.' },
    },
    bn: {
        meta: {
            title: 'ক্রমবর্ধমান ব্যবসার জন্য ইনভেন্টরি ম্যানেজমেন্ট',
            description:
                'Biponon আপনার স্টক, ক্রয় আর বিক্রয় এক জায়গায় রাখে, প্রতিটি গুদামে, আপনার পুরো টিমের জন্য।',
        },
        nav: {
            features: 'ফিচারসমূহ',
            howItWorks: 'কীভাবে কাজ করে',
            logIn: 'লগ ইন',
            dashboard: 'ড্যাশবোর্ড',
            requestAccess: 'অ্যাক্সেসের অনুরোধ',
            language: 'ভাষা',
        },
        hero: {
            eyebrow: 'ক্রমবর্ধমান ব্যবসার জন্য ইনভেন্টরি ম্যানেজমেন্ট',
            title: 'কী আছে, কোথায় আছে, কোথায় গেল, সব জানুন নিখুঁতভাবে।',
            subtitle:
                'Biponon আপনার স্টক, ক্রয় আর বিক্রয় এক জায়গায় রাখে, প্রতিটি গুদামে, আপনার পুরো টিমের জন্য।',
            note: 'শুধু আমন্ত্রণের মাধ্যমে। আপনার প্রতিষ্ঠান আমরাই সেটআপ করে দিই।',
        },
        preview: {
            unitsInStock: 'স্টকে মোট ইউনিট',
            lowStock: 'কম স্টক',
            warehouses: 'গুদাম',
            lastSevenDays: 'গত ৭ দিন',
            stockIn: 'এসেছে',
            stockOut: 'গেছে',
            recentMovements: 'সাম্প্রতিক মুভমেন্ট',
            received: 'গৃহীত',
            dispatched: 'পাঠানো হয়েছে',
            transferred: 'স্থানান্তরিত',
            units: 'ইউনিট',
        },
        features: {
            title: 'আপনার স্টকের সবকিছু, এক জায়গায়',
            subtitle: 'সরবরাহকারীর ট্রাক থেকে গ্রাহকের দরজা পর্যন্ত।',
            items: {
                stock: {
                    title: 'একাধিক গুদামে স্টক',
                    body: 'প্রতিটি সাইজ ও রঙের পরিমাণ প্রতিটি গুদামে সরাসরি দেখুন, আর প্রতিটি পণ্যের জন্য আলাদা লো-স্টক সীমা ঠিক করুন।',
                },
                serials: {
                    title: 'সিরিয়াল নম্বর',
                    body: 'প্রতিটি পণ্য সিরিয়াল নম্বর ধরে অনুসরণ করুন, আসার দিন থেকে বিক্রি বা ফেরত আসা পর্যন্ত।',
                },
                purchasing: {
                    title: 'ক্রয়',
                    body: 'পারচেজ অর্ডার তৈরি করুন, ডেলিভারি আংশিকভাবে বা একাধিক গুদামে ভাগ করে গ্রহণ করুন, আর ক্রয়মূল্য শুধু ম্যানেজারদের দেখান।',
                },
                sales: {
                    title: 'সেলস অর্ডার',
                    body: 'সেলস অর্ডার নিন, যেকোনো গুদাম থেকে পণ্য পাঠান, আর মূল অর্ডারের বিপরীতে ফেরত রেকর্ড করুন।',
                },
                reports: {
                    title: 'রিপোর্ট',
                    body: 'স্টকের মূল্য, বিক্রয়, সরবরাহকারী অনুযায়ী ক্রয় আর ধীরগতির স্টক, প্রতিটি CSV-তে এক্সপোর্ট করা যায়।',
                },
                roles: {
                    title: 'রোল ও অনুমতি',
                    body: 'অ্যাডমিন, ম্যানেজার ও স্টাফ প্রত্যেকে শুধু তাদের প্রয়োজনীয় অংশ দেখেন ও করেন, আর প্রতিটি স্টক মুভমেন্ট রেকর্ড থাকে।',
                },
            },
        },
        steps: {
            title: 'তিন ধাপে শুরু করুন',
            items: [
                {
                    title: 'আমরা আপনার প্রতিষ্ঠান সেটআপ করি',
                    body: 'অ্যাক্সেসের অনুরোধ পাঠান, আমরা আপনার প্রতিষ্ঠান তৈরি করে আপনাকে অ্যাডমিন আমন্ত্রণ পাঠাব।',
                },
                {
                    title: 'আপনার টিমকে আমন্ত্রণ জানান',
                    body: 'আপনার গুদাম ও পণ্য যোগ করুন, তারপর সঠিক রোল দিয়ে ম্যানেজার ও স্টাফদের আমন্ত্রণ জানান।',
                },
                {
                    title: 'গ্রহণ ও বিক্রি শুরু করুন',
                    body: 'প্রতিটি ডেলিভারি, স্থানান্তর, বিক্রয় ও ফেরত সঙ্গে সঙ্গে আপনার স্টক আপডেট করে।',
                },
            ],
        },
        requestAccess: {
            title: 'গুছিয়ে নিতে প্রস্তুত?',
            body: 'আপনার ব্যবসা সম্পর্কে একটু জানান। আমরা আপনার প্রতিষ্ঠান সেটআপ করে আপনাকে আমন্ত্রণ পাঠাব।',
        },
        footer: { rights: 'সর্বস্বত্ব সংরক্ষিত।' },
    },
};
