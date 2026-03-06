# Responsive Design Standards

Basil follows mobile-first principles with Tailwind CSS breakpoints.

---

## Breakpoints

Use Tailwind's standard breakpoints consistently:

| Breakpoint | Min Width | Usage          |
| ---------- | --------- | -------------- |
| `sm`       | 640px     | Small tablets  |
| `md`       | 768px     | Tablets        |
| `lg`       | 1024px    | Small laptops  |
| `xl`       | 1280px    | Desktops       |
| `2xl`      | 1536px    | Large desktops |

---

## Mobile-First Development

Start with mobile layout and progressively enhance:

```vue
<template>
    <!-- ✅ Good: mobile-first -->
    <div class="flex flex-col lg:flex-row">
        <aside class="w-full lg:w-64">Sidebar</aside>
        <main class="flex-1">Content</main>
    </div>
</template>
```

```vue
<template>
    <!-- ❌ Bad: desktop-first -->
    <div class="flex flex-row max-lg:flex-col">...</div>
</template>
```

---

## Layout Patterns

### Fluid Containers

Use percentage-based widths with max constraints:

```vue
<template>
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Content constrained but fluid -->
    </div>
</template>
```

### Responsive Grid

Adjust grid columns by breakpoint:

```vue
<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <Card v-for="item in items" :key="item.id" />
    </div>
</template>
```

### Stack to Row

Common pattern for form layouts:

```vue
<template>
    <form class="flex flex-col gap-4 sm:flex-row">
        <VInput class="flex-1" label="Search" />
        <VButton class="sm:w-auto">Search</VButton>
    </form>
</template>
```

---

## Typography

### Responsive Font Sizes

Maintain readability across viewports:

```vue
<template>
    <h1 class="text-2xl sm:text-3xl lg:text-4xl">Page Title</h1>
    <p class="text-sm sm:text-base">Body content at readable sizes</p>
</template>
```

### Line Length

Constrain text width for readability (45-75 characters):

```vue
<template>
    <article class="max-w-prose">
        <p>Long form content stays readable...</p>
    </article>
</template>
```

---

## Touch-Friendly Design

### Tap Targets

Ensure minimum 44x44px touch targets:

```vue
<template>
    <!-- ✅ Good: adequate touch target -->
    <button class="min-h-11 min-w-11 p-3">
        <Icon name="menu" />
    </button>

    <!-- ❌ Bad: too small for touch -->
    <button class="p-1">
        <Icon name="menu" />
    </button>
</template>
```

### Touch Spacing

Add adequate spacing between interactive elements:

```vue
<template>
    <nav class="flex gap-4">
        <a class="p-3" href="/home">Home</a>
        <a class="p-3" href="/about">About</a>
    </nav>
</template>
```

---

## Visibility by Breakpoint

### Show/Hide Content

Use responsive display utilities:

```vue
<template>
    <!-- Mobile navigation -->
    <nav class="lg:hidden">
        <MobileMenu />
    </nav>

    <!-- Desktop navigation -->
    <nav class="hidden lg:flex">
        <DesktopMenu />
    </nav>
</template>
```

### Content Priority

Show essential content first on mobile:

```vue
<template>
    <article>
        <!-- Always visible -->
        <h1>{{ title }}</h1>
        <p>{{ summary }}</p>

        <!-- Hidden on mobile, shown on larger screens -->
        <aside class="hidden md:block">
            <RelatedContent />
        </aside>
    </article>
</template>
```

---

## Images & Media

### Responsive Images

Serve appropriate image sizes:

```vue
<template>
    <img
        :src="imageUrl"
        :srcset="`${imageUrl}?w=400 400w, ${imageUrl}?w=800 800w`"
        sizes="(max-width: 640px) 100vw, 50vw"
        class="h-auto w-full"
        alt="Description"
    />
</template>
```

### Aspect Ratios

Maintain consistent proportions:

```vue
<template>
    <div class="aspect-video">
        <img class="h-full w-full object-cover" :src="imageUrl" alt="" />
    </div>
</template>
```

---

## Testing Checklist

- [ ] Test at all standard breakpoints (sm, md, lg, xl, 2xl)
- [ ] Verify no horizontal scrolling at any viewport
- [ ] Check touch targets are 44x44px minimum
- [ ] Ensure text remains readable without zooming
- [ ] Test with browser zoom at 200%
- [ ] Verify focus indicators are visible
- [ ] Test landscape orientation on mobile devices
