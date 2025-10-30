<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anda telah ditambahkan ke project</title>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: #374151;
        background-color: #f9fafb;
    }

    .email-container {
        max-width: 600px;
        margin: 0 auto;
        background-color: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 40px 30px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px) rotate(0deg);
        }

        50% {
            transform: translateY(-20px) rotate(180deg);
        }
    }

    .header-content {
        position: relative;
        z-index: 2;
    }

    .icon {
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
        }
    }

    .icon svg {
        width: 30px;
        height: 30px;
        fill: white;
    }

    .header h1 {
        color: white;
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 10px;
        animation: slideInDown 0.8s ease-out;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .header p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        animation: slideInUp 0.8s ease-out 0.2s both;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .content {
        padding: 40px 30px;
    }

    .greeting {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 20px;
        animation: fadeIn 0.8s ease-out 0.4s both;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .project-card {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 25px;
        margin: 25px 0;
        position: relative;
        overflow: hidden;
        animation: slideInLeft 0.8s ease-out 0.6s both;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .project-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .project-name {
        font-size: 22px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 10px;
    }

    .project-description {
        color: #6b7280;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 15px;
    }

    .project-description p {
        margin: 0 0 10px 0;
    }

    .project-description p:last-child {
        margin-bottom: 0;
    }

    .project-description strong,
    .project-description b {
        font-weight: 600;
        color: #374151;
    }

    .project-description em,
    .project-description i {
        font-style: italic;
    }

    .project-description ul,
    .project-description ol {
        margin: 10px 0;
        padding-left: 20px;
    }

    .project-description li {
        margin: 5px 0;
    }

    .project-description h1,
    .project-description h2,
    .project-description h3,
    .project-description h4,
    .project-description h5,
    .project-description h6 {
        margin: 15px 0 10px 0;
        font-weight: 600;
        color: #1f2937;
    }

    .project-description h1 {
        font-size: 18px;
    }

    .project-description h2 {
        font-size: 16px;
    }

    .project-description h3 {
        font-size: 15px;
    }

    .project-description h4 {
        font-size: 14px;
    }

    .project-description h5 {
        font-size: 13px;
    }

    .project-description h6 {
        font-size: 12px;
    }

    .project-description blockquote {
        margin: 10px 0;
        padding: 10px 15px;
        border-left: 3px solid #e5e7eb;
        background-color: #f9fafb;
        font-style: italic;
    }

    .project-description code {
        background-color: #f3f4f6;
        padding: 2px 4px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        font-size: 13px;
    }

    .project-description pre {
        background-color: #f3f4f6;
        padding: 10px;
        border-radius: 5px;
        overflow-x: auto;
        margin: 10px 0;
    }

    .project-description a {
        color: #667eea;
        text-decoration: none;
    }

    .project-description a:hover {
        text-decoration: underline;
    }

    .assigned-by {
        background: rgba(99, 102, 241, 0.1);
        border-radius: 8px;
        padding: 15px;
        margin: 20px 0;
        animation: slideInRight 0.8s ease-out 0.8s both;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(30px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .assigned-by-label {
        font-size: 12px;
        font-weight: 600;
        color: #6366f1;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    .assigned-by-name {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .cta-button {
        display: inline-block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        padding: 16px 32px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        text-align: center;
        margin: 30px 0;
        transition: all 0.3s ease;
        animation: bounceIn 0.8s ease-out 1s both;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    @keyframes bounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3);
        }

        50% {
            opacity: 1;
            transform: scale(1.05);
        }

        70% {
            transform: scale(0.9);
        }

        100% {
            opacity: 1;
            transform: scale(1);
        }
    }

    .cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }

    .footer {
        background-color: #f8fafc;
        padding: 30px;
        text-align: center;
        border-top: 1px solid #e2e8f0;
    }

    .footer p {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 10px;
    }

    .footer a {
        color: #667eea;
        text-decoration: none;
    }

    .footer a:hover {
        text-decoration: underline;
    }

    .divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
        margin: 25px 0;
    }

    @media (max-width: 600px) {
        .email-container {
            margin: 0;
            border-radius: 0;
        }

        .header,
        .content,
        .footer {
            padding: 30px 20px;
        }

        .header h1 {
            font-size: 24px;
        }

        .project-name {
            font-size: 20px;
        }

        .cta-button {
            display: block;
            width: 100%;
            text-align: center;
        }
    }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <div class="header-content">
                <div class="icon">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                    </svg>
                </div>
                <h1>Selamat Bergabung!</h1>
                <p>Anda telah ditambahkan ke project baru</p>
            </div>
        </div>

        <div class="content">
            <div class="greeting">
                Halo {{ $assignedUser->name }}! 👋
            </div>

            <p style="color: #6b7280; margin-bottom: 25px; animation: fadeIn 0.8s ease-out 0.5s both;">
                Kami senang menginformasikan bahwa Anda telah ditambahkan ke project berikut:
            </p>

            <div class="project-card">
                <div class="project-name">{{ $project->name }}</div>
                @if($project->description)
                <div class="project-description">{!! $project->description !!}</div>
                @endif

                <div class="assigned-by">
                    <div class="assigned-by-label">Ditambahkan oleh</div>
                    <div class="assigned-by-name">{{ $assignedBy->name }}</div>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{{ $projectUrl }}" class="cta-button">
                    🚀 Lihat Project
                </a>
            </div>

            <div class="divider"></div>

            <p style="color: #6b7280; font-size: 14px; text-align: center;">
                Klik tombol di atas untuk mengakses project dan mulai berkontribusi!
            </p>
        </div>

        <div class="footer">
            <p>Email ini dikirim secara otomatis dari sistem Project Management.</p>
            <p>
                <a href="{{ config('app.url') }}">Kunjungi Dashboard</a> |
                <a href="{{ config('app.url') }}/admin/notifications">Kelola Notifikasi</a>
            </p>
        </div>
    </div>
</body>

</html>