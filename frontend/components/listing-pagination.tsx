import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";

/**
 * Numbered page links under the product grid, keeping the search in every link.
 * Adapted from Your Next Store's listing pagination (MIT, see THIRD_PARTY_NOTICES.md).
 */

/** 1 … 4 5 6 … 12: first, last, and the pages next to the current one. */
function getPageNumbers(currentPage: number, totalPages: number) {
  const pages = Array.from({ length: totalPages }, (_, i) => i + 1);

  return pages.reduce<(number | "ellipsis")[]>((acc, page) => {
    if (page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)) {
      acc.push(page);
      return acc;
    }
    if (acc[acc.length - 1] !== "ellipsis") {
      acc.push("ellipsis");
    }
    return acc;
  }, []);
}

function buildUrl(basePath: string, page: number, search: string) {
  const params = new URLSearchParams();
  if (search) params.set("search", search);
  if (page > 1) params.set("page", String(page));

  return params.size > 0 ? `${basePath}?${params}` : basePath;
}

export function ListingPagination({
  basePath,
  currentPage,
  totalPages,
  search = "",
}: {
  basePath: string;
  currentPage: number;
  totalPages: number;
  search?: string;
}) {
  if (totalPages <= 1) return null;

  return (
    <Pagination className="mt-12">
      <PaginationContent>
        {currentPage > 1 && (
          <PaginationItem>
            <PaginationPrevious href={buildUrl(basePath, currentPage - 1, search)} />
          </PaginationItem>
        )}
        {getPageNumbers(currentPage, totalPages).map((page, index) =>
          page === "ellipsis" ? (
            <PaginationItem key={`ellipsis-${index}`}>
              <PaginationEllipsis />
            </PaginationItem>
          ) : (
            <PaginationItem key={page}>
              <PaginationLink href={buildUrl(basePath, page, search)} isActive={page === currentPage}>
                {page}
              </PaginationLink>
            </PaginationItem>
          ),
        )}
        {currentPage < totalPages && (
          <PaginationItem>
            <PaginationNext href={buildUrl(basePath, currentPage + 1, search)} />
          </PaginationItem>
        )}
      </PaginationContent>
    </Pagination>
  );
}
