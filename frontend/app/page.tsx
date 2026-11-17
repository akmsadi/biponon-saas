/** shop.mypersonalwork.com/ itself: each business's shop lives under its own path, e.g. /demo-fashion. */
export default function Home() {
  return (
    <main className="flex flex-1 items-center justify-center px-4 py-24">
      <div className="max-w-md text-center">
        <h1 className="text-3xl font-semibold tracking-tight text-foreground">Biponon shops</h1>
        <p className="mt-4 text-muted-foreground">
          Online shops for businesses that run their inventory on Biponon. Open the link your shop gave you to
          browse its products.
        </p>
      </div>
    </main>
  );
}
