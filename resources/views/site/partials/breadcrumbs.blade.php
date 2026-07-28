{{--
    Visible breadcrumb trail. The matching BreadcrumbList JSON-LD is emitted from
    the same `$breadcrumbs` array in site.partials.jsonld, so the markup a visitor
    reads and the markup a crawler reads cannot describe different paths.

    The last item is the current page: rendered as text with aria-current rather
    than as a link to itself.
--}}
@if (! empty($breadcrumbs))
    <nav aria-label="{{ __('marketing.nav.label') }}" class="container-page pt-8">
        <ol role="list" class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-faint">
            @foreach ($breadcrumbs as $index => $crumb)
                <li class="flex items-center gap-2">
                    @if ($index > 0)
                        <span aria-hidden="true" class="text-line-bright">/</span>
                    @endif

                    @if ($loop->last)
                        <span aria-current="page" class="text-muted">{{ $crumb['title'] }}</span>
                    @else
                        <a href="{{ $crumb['url'] }}" class="transition-colors hover:text-accent-soft">
                            {{ $crumb['title'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
