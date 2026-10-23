@foreach ($categories->where('parent_id', $parentId) as $category)
    <tr>
        <td class="px-4 py-3" style="padding-inline-start: calc(1rem + {{ $depth * 1.5 }}rem)">{{ $category->name }}</td>
        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $category->slug }}</td>
        <td class="px-4 py-3">
            <x-badge :status="$category->is_active ? 'active' : 'inactive'">{{ $category->is_active ? __('common.labels.active') : __('common.labels.inactive') }}</x-badge>
        </td>
        <td class="px-4 py-3">
            <div class="flex gap-3">
                @can('update', $category)
                    <a href="{{ route('admin.categories.edit', $category) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.edit') }}</a>
                @endcan
                @can('delete', $category)
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="{{ __('admin.categories.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('common.actions.delete') }}</button>
                    </form>
                @endcan
            </div>
        </td>
    </tr>
    @include('admin.categories._tree', ['categories' => $categories, 'parentId' => $category->id, 'depth' => $depth + 1])
@endforeach
