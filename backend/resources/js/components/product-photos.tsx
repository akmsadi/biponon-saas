import { Form } from '@inertiajs/react';
import { ImagePlus, Star, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import ProductImageController from '@/actions/App/Http/Controllers/Catalog/ProductImageController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export type ProductPhoto = { id: number; url: string };

/**
 * A product's online shop photos. Managers add, reorder (pick the main one) and delete; members only look.
 */
export default function ProductPhotos({
    product,
    images,
    maxImages,
    canManage,
}: {
    product: { id: number; name: string };
    images: ProductPhoto[];
    maxImages: number;
    canManage: boolean;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const room = maxImages - images.length;

    return (
        <Card className="max-w-2xl">
            <CardHeader>
                <CardTitle>Photos</CardTitle>
                <CardDescription>
                    Shown in the online shop. The first photo is the main one.
                    {canManage &&
                        ` JPG, PNG or WebP, up to 4 MB each, ${maxImages} photos at most.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No photos yet.
                    </p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {images.map((image, index) => (
                            <li
                                key={image.id}
                                className="group relative overflow-hidden rounded-lg border bg-muted"
                            >
                                <img
                                    src={image.url}
                                    alt={`${product.name}, photo ${index + 1}`}
                                    loading="lazy"
                                    className="aspect-square w-full object-cover"
                                />
                                {index === 0 && (
                                    <Badge className="absolute top-2 left-2">
                                        Main
                                    </Badge>
                                )}
                                {canManage && (
                                    <div className="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-gradient-to-t from-black/60 to-transparent p-2">
                                        {index > 0 && (
                                            <Form
                                                {...ProductImageController.makeMain.form(
                                                    image,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        size="icon"
                                                        variant="secondary"
                                                        className="size-7"
                                                        disabled={processing}
                                                        title="Make main photo"
                                                    >
                                                        <Star />
                                                        <span className="sr-only">
                                                            Make photo{' '}
                                                            {index + 1} the main
                                                            photo
                                                        </span>
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                        <Form
                                            {...ProductImageController.destroy.form(
                                                image,
                                            )}
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    size="icon"
                                                    variant="destructive"
                                                    className="size-7"
                                                    disabled={processing}
                                                    title="Delete photo"
                                                >
                                                    <Trash2 />
                                                    <span className="sr-only">
                                                        Delete photo {index + 1}
                                                    </span>
                                                </Button>
                                            )}
                                        </Form>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {canManage && (
                    <Form
                        {...ProductImageController.store.form(product)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                    >
                        {({ processing, errors, progress, submit }) => {
                            const fileErrors = Object.entries(errors)
                                .filter(([key]) => key.startsWith('images'))
                                .map(([, message]) => message);

                            return (
                                <div className="space-y-2">
                                    <input
                                        ref={fileInput}
                                        type="file"
                                        name="images[]"
                                        accept="image/jpeg,image/png,image/webp"
                                        multiple
                                        className="sr-only"
                                        tabIndex={-1}
                                        onChange={(event) =>
                                            event.target.files?.length &&
                                            submit()
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing || room <= 0}
                                        onClick={() =>
                                            fileInput.current?.click()
                                        }
                                    >
                                        <ImagePlus />
                                        {processing
                                            ? `Uploading${progress ? ` ${progress.percentage}%` : ''}…`
                                            : room > 0
                                              ? 'Add photos'
                                              : 'Photo limit reached'}
                                    </Button>
                                    {[...new Set(fileErrors)].map((message) => (
                                        <InputError
                                            key={message}
                                            message={message}
                                        />
                                    ))}
                                </div>
                            );
                        }}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
