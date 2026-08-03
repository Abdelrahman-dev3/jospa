<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::latest('published_at')
            ->latest()
            ->paginate(10);

        return view('backend.blog.index', compact('posts'));
    }

    public function create(): View
    {
        $post = new BlogPost([
            'is_active' => true,
            'published_at' => now(),
        ]);

        return view('backend.blog.create', compact('post'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        BlogPost::create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['title']),
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'image' => $this->storeImage($request),
            'is_active' => $request->boolean('is_active'),
            'published_at' => $validated['published_at'] ?? now(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('app.blog.index')->with('success', 'تم حفظ المقال بنجاح');
    }

    public function edit(BlogPost $blogPost): View
    {
        return view('backend.blog.edit', ['post' => $blogPost]);
    }

    public function update(Request $request, BlogPost $blogPost): RedirectResponse
    {
        $validated = $this->validatedData($request, $blogPost->id);
        $imagePath = $blogPost->image;

        if ($request->boolean('remove_image')) {
            $this->deleteImage($blogPost->image);
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($blogPost->image);
            $imagePath = $this->storeImage($request);
        }

        $blogPost->update([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['slug'] ?? $validated['title'], $blogPost->id),
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'image' => $imagePath,
            'is_active' => $request->boolean('is_active'),
            'published_at' => $validated['published_at'] ?? now(),
        ]);

        return redirect()->route('app.blog.index')->with('success', 'تم تحديث المقال بنجاح');
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $this->deleteImage($blogPost->image);
        $blogPost->delete();

        return redirect()->route('app.blog.index')->with('success', 'تم حذف المقال بنجاح');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);

        if ($base === '') {
            $base = 'blog-post';
        }

        $slug = $base;
        $counter = 2;

        while (
            BlogPost::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $directory = public_path('uploads/blogs');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $image = $request->file('image');
        $imageName = time().'_'.uniqid().'.'.$image->getClientOriginalExtension();
        $image->move($directory, $imageName);

        return 'uploads/blogs/'.$imageName;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
