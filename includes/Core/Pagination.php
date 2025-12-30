<?php
namespace ProConsultancy\Core;

/**
 * Pagination Class
 * Handles pagination logic and HTML rendering
 * 
 * @package ProConsultancy\Core
 * @version 5.0
 */
class Pagination {
    
    private int $totalItems;
    private int $itemsPerPage;
    private int $currentPage;
    private int $totalPages;
    private string $baseUrl;
    private int $maxLinks;
    private array $queryParams;
    
    /**
     * Constructor
     * 
     * @param int $totalItems Total number of items
     * @param int $itemsPerPage Number of items per page (default: 25)
     * @param int $currentPage Current page number (default: 1)
     * @param int $maxLinks Maximum number of page links to display (default: 7)
     */
    public function __construct(
        int $totalItems, 
        int $itemsPerPage = 25, 
        int $currentPage = 1,
        int $maxLinks = 7
    ) {
        $this->totalItems = max(0, $totalItems);
        $this->itemsPerPage = max(1, $itemsPerPage);
        $this->maxLinks = max(3, $maxLinks);
        
        // Calculate total pages
        $this->totalPages = max(1, (int) ceil($this->totalItems / $this->itemsPerPage));
        
        // Validate and set current page
        $this->currentPage = max(1, min($currentPage, $this->totalPages));
        
        // Parse current URL and query parameters
        $this->parseUrl();
    }

    /**
     * Get SQL LIMIT clause for queries
     * 
     * @return string
     */
    public function getLimitClause(): string {
        return 'LIMIT ' . $this->getOffset() . ', ' . $this->getLimit();
    }
    
    /**
     * Parse current URL and extract query parameters
     */
    private function parseUrl(): void {
        $this->queryParams = $_GET ?? [];
        
        // Remove page parameter from query params (we'll add it back in links)
        unset($this->queryParams['page']);
        
        // Get base URL (without query string)
        $urlParts = parse_url($_SERVER['REQUEST_URI'] ?? '');
        $this->baseUrl = $urlParts['path'] ?? '';
    }
    
    /**
     * Get SQL OFFSET for database queries
     * 
     * @return int Offset value
     */
    public function getOffset(): int {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    /**
     * Get SQL LIMIT for database queries
     * 
     * @return int Limit value
     */
    public function getLimit(): int {
        return $this->itemsPerPage;
    }
    
    /**
     * Get current page number
     * 
     * @return int Current page
     */
    public function getCurrentPage(): int {
        return $this->currentPage;
    }
    
    /**
     * Get total number of pages
     * 
     * @return int Total pages
     */
    public function getTotalPages(): int {
        return $this->totalPages;
    }
    
    /**
     * Get total number of items
     * 
     * @return int Total items
     */
    public function getTotalItems(): int {
        return $this->totalItems;
    }
    
    /**
     * Get items per page
     * 
     * @return int Items per page
     */
    public function getItemsPerPage(): int {
        return $this->itemsPerPage;
    }
    
    /**
     * Check if pagination is needed (more than one page)
     * 
     * @return bool True if multiple pages exist
     */
    public function hasPages(): bool {
        return $this->totalPages > 1;
    }
    
    /**
     * Check if there's a previous page
     * 
     * @return bool True if previous page exists
     */
    public function hasPrevious(): bool {
        return $this->currentPage > 1;
    }
    
    /**
     * Check if there's a next page
     * 
     * @return bool True if next page exists
     */
    public function hasNext(): bool {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Get first item number on current page
     * 
     * @return int First item number
     */
    public function getFirstItem(): int {
        if ($this->totalItems === 0) {
            return 0;
        }
        return ($this->currentPage - 1) * $this->itemsPerPage + 1;
    }
    
    /**
     * Get last item number on current page
     * 
     * @return int Last item number
     */
    public function getLastItem(): int {
        if ($this->totalItems === 0) {
            return 0;
        }
        return min($this->currentPage * $this->itemsPerPage, $this->totalItems);
    }
    
    /**
     * Build URL for a specific page
     * 
     * @param int $page Page number
     * @return string Complete URL
     */
    private function buildUrl(int $page): string {
        $params = $this->queryParams;
        $params['page'] = $page;
        
        $queryString = http_build_query($params);
        return $this->baseUrl . ($queryString ? '?' . $queryString : '');
    }
    
    /**
     * Calculate which page numbers to display
     * 
     * @return array Array of page numbers to display
     */
    private function getPageRange(): array {
        if ($this->totalPages <= $this->maxLinks) {
            // Show all pages if total is less than max
            return range(1, $this->totalPages);
        }
        
        $halfLinks = floor($this->maxLinks / 2);
        
        // Calculate start and end
        if ($this->currentPage <= $halfLinks) {
            // Near the beginning
            return range(1, $this->maxLinks);
        } elseif ($this->currentPage >= $this->totalPages - $halfLinks) {
            // Near the end
            return range($this->totalPages - $this->maxLinks + 1, $this->totalPages);
        } else {
            // In the middle
            return range(
                $this->currentPage - $halfLinks,
                $this->currentPage + $halfLinks
            );
        }
    }
    
    /**
     * Render Bootstrap 5 pagination HTML
     * 
     * @param string $size Size class: 'sm', 'lg', or '' for default
     * @param string $alignment Alignment: 'start', 'center', 'end'
     * @return string HTML markup
     */
    public function render(string $size = '', string $alignment = 'end'): string {
        if (!$this->hasPages()) {
            return '';
        }
        
        $sizeClass = $size ? " pagination-{$size}" : '';
        $alignClass = $alignment !== 'start' ? " justify-content-{$alignment}" : '';
        
        $html = '<nav aria-label="Page navigation">';
        $html .= '<ul class="pagination' . $sizeClass . $alignClass . ' mb-0">';
        
        // Previous button
        if ($this->hasPrevious()) {
            $prevUrl = $this->buildUrl($this->currentPage - 1);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . htmlspecialchars($prevUrl) . '" aria-label="Previous">';
            $html .= '<span aria-hidden="true">&laquo;</span>';
            $html .= '</a>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">&laquo;</span>';
            $html .= '</li>';
        }
        
        // Page numbers
        $pageRange = $this->getPageRange();
        
        // First page + ellipsis if needed
        if ($pageRange[0] > 1) {
            $firstUrl = $this->buildUrl(1);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . htmlspecialchars($firstUrl) . '">1</a>';
            $html .= '</li>';
            
            if ($pageRange[0] > 2) {
                $html .= '<li class="page-item disabled">';
                $html .= '<span class="page-link">...</span>';
                $html .= '</li>';
            }
        }
        
        // Page links
        foreach ($pageRange as $page) {
            if ($page === $this->currentPage) {
                $html .= '<li class="page-item active" aria-current="page">';
                $html .= '<span class="page-link">' . $page . '</span>';
                $html .= '</li>';
            } else {
                $pageUrl = $this->buildUrl($page);
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="' . htmlspecialchars($pageUrl) . '">' . $page . '</a>';
                $html .= '</li>';
            }
        }
        
        // Ellipsis + last page if needed
        if (end($pageRange) < $this->totalPages) {
            if (end($pageRange) < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled">';
                $html .= '<span class="page-link">...</span>';
                $html .= '</li>';
            }
            
            $lastUrl = $this->buildUrl($this->totalPages);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . htmlspecialchars($lastUrl) . '">' . $this->totalPages . '</a>';
            $html .= '</li>';
        }
        
        // Next button
        if ($this->hasNext()) {
            $nextUrl = $this->buildUrl($this->currentPage + 1);
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . htmlspecialchars($nextUrl) . '" aria-label="Next">';
            $html .= '<span aria-hidden="true">&raquo;</span>';
            $html .= '</a>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">&raquo;</span>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Render pagination info text (e.g., "Showing 1-25 of 100 results")
     * 
     * @param string $itemName Name of items (default: 'results')
     * @return string HTML markup
     */
    public function renderInfo(string $itemName = 'results'): string {
        if ($this->totalItems === 0) {
            return '<div class="text-muted">No ' . htmlspecialchars($itemName) . ' found</div>';
        }
        
        $first = $this->getFirstItem();
        $last = $this->getLastItem();
        $total = $this->totalItems;
        
        return '<div class="text-muted">' .
               'Showing ' . number_format($first) . '-' . number_format($last) . 
               ' of ' . number_format($total) . ' ' . htmlspecialchars($itemName) .
               '</div>';
    }
    
    /**
     * Render complete pagination with info and links
     * 
     * @param string $itemName Name of items (default: 'results')
     * @param string $size Pagination size: 'sm', 'lg', or ''
     * @return string HTML markup
     */
    public function renderComplete(string $itemName = 'results', string $size = ''): string {
        if ($this->totalItems === 0) {
            return $this->renderInfo($itemName);
        }
        
        $html = '<div class="d-flex justify-content-between align-items-center">';
        $html .= $this->renderInfo($itemName);
        
        if ($this->hasPages()) {
            $html .= $this->render($size, 'end');
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render items-per-page dropdown
     * 
     * @param array $options Available page sizes (default: [10, 25, 50, 100])
     * @return string HTML markup
     */
    public function renderPerPageSelector(array $options = [10, 25, 50, 100]): string {
        $html = '<div class="d-flex align-items-center gap-2">';
        $html .= '<label class="text-muted mb-0">Show:</label>';
        $html .= '<select class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=this.value">';
        
        foreach ($options as $option) {
            $params = $this->queryParams;
            $params['per_page'] = $option;
            $params['page'] = 1; // Reset to page 1 when changing items per page
            
            $url = $this->baseUrl . '?' . http_build_query($params);
            $selected = ($option === $this->itemsPerPage) ? ' selected' : '';
            
            $html .= '<option value="' . htmlspecialchars($url) . '"' . $selected . '>';
            $html .= $option . ' per page';
            $html .= '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Static factory method for quick creation from request
     * 
     * @param int $totalItems Total number of items
     * @param int $defaultPerPage Default items per page
     * @return self
     */
    public static function fromRequest(int $totalItems, int $defaultPerPage = 25): self {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : $defaultPerPage;
        
        // Validate per_page
        $allowedPerPage = [10, 25, 50, 100, 250];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = $defaultPerPage;
        }
        
        return new self($totalItems, $perPage, $page);
    }
}