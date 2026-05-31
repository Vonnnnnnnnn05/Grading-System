<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $pageTitle ?? 'Online Grading System' ?> | GradeSync</title>
  <meta name="description" content="GradeSync – Online Grading System for administrators, teachers, and students." />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary:  { DEFAULT: '#2563EB', light: '#EFF6FF', dark: '#1D4ED8' },
            surface:  '#F8FAFC',
            border:   '#E5E7EB',
            success:  '#16A34A',
            danger:   '#DC2626',
            warning:  '#D97706',
            muted:    '#6B7280',
          },
          fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .sidebar-link { transition: all 0.2s ease; }
    .sidebar-link:hover { background-color: rgba(255,255,255,0.12); }
    .sidebar-link.active { background-color: rgba(255,255,255,0.18); }
    .fade-in { animation: fadeIn 0.35s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .card-hover { transition: box-shadow 0.2s, transform 0.2s; }
    .card-hover:hover { box-shadow: 0 8px 25px rgba(37,99,235,0.12); transform: translateY(-2px); }
    ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f1f5f9; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
  </style>
</head>
<body class="bg-surface text-gray-800 min-h-screen">
