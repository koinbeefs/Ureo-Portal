# DRD Design System Documentation
## Tarlac Agricultural University - Department of Research and Development

*Last Updated: February 2, 2026*

---

## 🎨 Color Palette

### Primary Colors
```css
/* Main Brand Colors */
--drd-dark-green: #006400;
--drd-forest-green: #228B22;
--drd-very-dark-green: #0f2a0f;
--drd-light-green: #90EE90;

/* Primary Gradient */
background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%);

/* Alternative Gradient */
background: linear-gradient(135deg, #0f7a2a, #0b3b18);
```

### Neutral Colors
```css
/* Backgrounds */
--bg-light: #F8F8F8;
--bg-white: #FFFFFF;
--bg-dark: #1a1a1a;
--bg-darker: #121212;

/* Text Colors */
--text-primary: #333333;
--text-secondary: #666666;
--text-muted: #999999;
--text-light: #CCCCCC;

/* Border Colors */
--border-light: #EAEAEA;
--border-default: #ddd;
--border-dark: #333333;
```

### Semantic Colors
```css
/* Success */
--success-bg: #d4edda;
--success-text: #155724;
--success-border: #c3e6cb;

/* Error */
--error-bg: #f8d7da;
--error-text: #721c24;
--error-border: #f5c6cb;

/* Info */
--info-bg: #d1ecf1;
--info-text: #0c5460;
--info-border: #bee5eb;
```

---

## 📏 Typography

### Font Family
```css
font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
```

### Font Sizes
```css
/* Headings */
--h1-size: 48px;      /* Hero titles */
--h2-size: 42px;      /* Section titles */
--h3-size: 32px;      /* Subsection titles */
--h4-size: 24px;      /* Card titles */
--h5-size: 20px;      /* Small titles */
--h6-size: 18px;      /* Minor headings */

/* Body Text */
--text-large: 18px;
--text-default: 16px;
--text-small: 14px;
--text-tiny: 12px;

/* Font Weights */
--weight-normal: 400;
--weight-medium: 500;
--weight-semibold: 600;
--weight-bold: 700;
--weight-extrabold: 800;
```

### Line Heights
```css
--line-height-tight: 1.2;
--line-height-normal: 1.4;
--line-height-relaxed: 1.6;
--line-height-loose: 1.8;
```

### Letter Spacing
```css
--letter-spacing-tight: 0.2px;
--letter-spacing-normal: 0.3px;
--letter-spacing-wide: 0.5px;
--letter-spacing-wider: 1px;
```

---

## 📐 Spacing System

### Padding & Margin
```css
/* Base unit: 4px */
--space-xs: 4px;
--space-sm: 8px;
--space-md: 12px;
--space-lg: 16px;
--space-xl: 20px;
--space-2xl: 24px;
--space-3xl: 30px;
--space-4xl: 40px;
--space-5xl: 60px;
--space-6xl: 80px;
```

### Gap Spacing
```css
--gap-sm: 10px;
--gap-md: 18px;
--gap-lg: 24px;
--gap-xl: 30px;
```

---

## 🔲 Border Radius

```css
--radius-sm: 4px;     /* Buttons, inputs */
--radius-md: 8px;     /* Cards */
--radius-lg: 12px;    /* Large cards */
--radius-xl: 16px;    /* Featured sections */
--radius-2xl: 20px;   /* Hero sections */
--radius-full: 50%;   /* Circles */
--radius-pill: 999px; /* Pills/badges */
```

---

## 🌑 Box Shadows

```css
/* Elevation Levels */
--shadow-xs: 0 2px 4px rgba(0, 0, 0, 0.08);
--shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.1);
--shadow-md: 0 6px 18px rgba(0, 0, 0, 0.08);
--shadow-lg: 0 8px 25px rgba(0, 0, 0, 0.1);
--shadow-xl: 0 10px 30px rgba(0, 0, 0, 0.15);
--shadow-2xl: 0 20px 40px rgba(0, 0, 0, 0.2);
--shadow-3xl: 0 24px 60px rgba(0, 0, 0, 0.35);

/* Green Shadows (for emphasis) */
--shadow-green-sm: 0 4px 12px rgba(15, 122, 42, 0.2);
--shadow-green-md: 0 8px 24px rgba(15, 122, 42, 0.25);
--shadow-green-lg: 0 10px 25px rgba(15, 122, 42, 0.3);
```

---

## 🎭 Visual Effects

### Gradients
```css
/* Primary Header Gradient */
background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%);

/* Card Gradient */
background: linear-gradient(135deg, #0f7a2a, #0b3b18);

/* Overlay Gradients */
background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.72) 100%);
```

### Glass Morphism
```css
background: rgba(255, 255, 255, 0.95);
backdrop-filter: blur(10px);
border: 1px solid rgba(15, 122, 42, 0.1);
```

### Text Gradient
```css
background: linear-gradient(135deg, #0f7a2a, #0b3b18);
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
```

---

## 🔄 Transitions & Animations

### Standard Transitions
```css
transition: all 0.3s ease;              /* General use */
transition: background-color 0.3s ease; /* Colors */
transition: transform 0.3s ease;        /* Movements */
transition: opacity 0.3s ease;          /* Fading */
```

### Transform Effects
```css
/* Hover Lift */
transform: translateY(-5px);

/* Scale */
transform: scale(1.05);

/* Perspective */
transform: perspective(1000px) rotateY(-5deg);
```

### Keyframe Animations
```css
/* Float Effect */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

/* Pulse Effect */
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Modal Entry */
@keyframes drdModalIn {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(8px);
    }
}
```

---

## 📱 Responsive Breakpoints

```css
/* Mobile First Approach */
@media (max-width: 480px) { /* Small phones */ }
@media (max-width: 560px) { /* Phones */ }
@media (max-width: 768px) { /* Tablets */ }
@media (max-width: 1000px) { /* Small desktops */ }
```

### Grid Adjustments
```css
/* 3-column -> 2-column -> 1-column */
grid-template-columns: repeat(3, minmax(0, 1fr));

@media (max-width: 1000px) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (max-width: 560px) {
    grid-template-columns: 1fr;
}
```

---

## 🏗️ Layout Structure

### Container
```css
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}
```

### Fixed Header
```css
.header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 82px;
    z-index: 9999;
}
```

### Sidebar
```css
.sidebar {
    position: fixed;
    top: 0;
    left: -280px;
    width: 280px;
    height: 100vh;
    z-index: 1002;
}

.sidebar.active {
    left: 0;
}
```

### Main Content
```css
.main-content {
    margin-top: 35px;
    min-height: calc(100vh - 82px);
}
```

---

## 🎯 Design Principles

### 1. **Academic & Professional**
- Formal tone suitable for university department
- Clean, organized layouts
- Emphasis on readability and clarity

### 2. **Green Agricultural Theme**
- Consistent use of green color palette
- Nature-inspired gradients
- Represents sustainability and agriculture

### 3. **User-Centered Design**
- Clear navigation hierarchy
- Intuitive interactions
- Mobile-responsive layouts

### 4. **Visual Hierarchy**
- Bold headings with gradient text
- Ample white space
- Consistent card-based layouts

### 5. **Accessibility**
- Semantic HTML structure
- ARIA labels for interactive elements
- Keyboard navigation support
- Sufficient color contrast

### 6. **Performance**
- Optimized images
- Caching system for search results
- Efficient CSS animations

---

## 🎨 Usage Guidelines

### When to Use Gradients
- ✅ Headers and hero sections
- ✅ Call-to-action buttons
- ✅ Featured cards and highlights
- ❌ Body text containers
- ❌ Form inputs

### When to Use Shadows
- ✅ Cards and elevated content
- ✅ Modal dialogs
- ✅ Dropdown menus
- ❌ Inline text elements
- ❌ Flat UI components

### When to Use Animations
- ✅ Hover states for interactivity
- ✅ Page transitions
- ✅ Loading indicators
- ❌ Continuous background animations
- ❌ Distracting movements

---

## 📚 Component Categories

### Navigation
- Fixed header with dropdown menus
- Collapsible sidebar
- Breadcrumb navigation

### Content Display
- Hero carousel
- News cards with images
- Milestone timeline
- Bulletin/Poster grids
- Facility photo grid

### Interactive Elements
- Modal dialogs
- Search interface
- Image galleries
- Sliders/Carousels

### Forms
- Input fields with focus states
- File upload components
- WYSIWYG editor integration

### Data Display
- Statistics cards (KPI)
- Research listings
- Publication archives

---

## 🔐 Security Patterns

### CSRF Protection
```php
// Generate token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Verify token
hash_equals($_SESSION['csrf_token'], $token);
```

### Input Sanitization
```php
$data = trim($data);
$data = stripslashes($data);
$data = htmlspecialchars($data);
```

### Database Security
```php
// Always use prepared statements
$stmt = $db->prepare($sql);
$stmt->bindValue(':param', $value, PDO::PARAM_INT);
$stmt->execute();
```

---

## 📞 Support & Maintenance

**Project Type:** PHP-based CMS for Academic Department  
**Database:** MySQL/MariaDB  
**Framework:** Custom (no framework dependency)  
**Browser Support:** Modern browsers (Chrome, Firefox, Edge, Safari)  

**Key Files:**
- `/css/style.css` - Main stylesheet (1670 lines)
- `/js/script.js` - Main JavaScript (674 lines)
- `/functions.php` - Core PHP functions (2408 lines)
- `/db.php` - Database configuration

**Admin Panel:** `/admin/` directory  
**Default Credentials:** root/admin (change in production!)

---

*This design system ensures consistency across all pages and components in the DRD website.*
