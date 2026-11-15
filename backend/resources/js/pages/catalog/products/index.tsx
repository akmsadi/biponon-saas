import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import Heading from '@/components/heading';
import ProductFields from '@/components/product-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { index, show } from '@/routes/catalog/products';

type Product = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    product_variants_count: number;
    in_stock_count: number;
};

export default function Products({ products }: { products: Product[] }) {
    const [creating, setCreating] = useState(false);
    const canManage = usePage().props.auth.can.manageCatalog;

    return (
        <>
            <Head title="Products" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Products"
                        description="What you sell, with a variant for each size and color"
                    />
                    {canManage && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus /> Add product
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">
                                    Variants
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    In stock
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No products yet. Add one, then give it
                                        size and color variants.
                                    </td>
                                </tr>
                            )}
                            {products.map((product) => (
                                <tr
                                    key={product.id}
                                    className="border-b last:border-0 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show(product)}
                                            className="font-medium hover:underline"
                                        >
                                            {product.name}
                                        </Link>
                                        {product.description && (
                                            <div className="line-clamp-1 text-xs text-muted-foreground">
                                                {product.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {product.product_variants_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        {product.in_stock_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                product.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {product.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent>
                    <DialogTitle>Add product</DialogTitle>
                    <DialogDescription>
                        You'll add its sizes and colors on the next page.
                    </DialogDescription>

                    <Form
                        {...ProductController.store.form()}
                        onSuccess={() => setCreating(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <ProductFields errors={errors} />

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button disabled={processing}>
                                        Add product
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Products.layout = {
    breadcrumbs: [
        {
            title: 'Products',
            href: index(),
        },
    ],
};
