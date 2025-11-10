<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Overdue</title>
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
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        padding: 40px 30px;
        text-align: center;
    }

    .header h1 {
        color: white;
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .header p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
    }

    .content {
        padding: 40px 30px;
    }

    .greeting {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 20px;
    }

    .project-card {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        border: 2px solid #ef4444;
        border-radius: 12px;
        padding: 25px;
        margin: 25px 0;
    }

    .project-card::before {
        content: '';
        display: block;
        height: 4px;
        background: linear-gradient(90deg, #ef4444, #dc2626);
        margin: -25px -25px 20px -25px;
        border-radius: 12px 12px 0 0;
    }

    .project-name {
        font-size: 22px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 10px;
    }

    .project-details {
        color: #6b7280;
        font-size: 14px;
        margin: 15px 0;
    }

    .project-details strong {
        color: #374151;
        font-weight: 600;
    }

    .overdue-badge {
        display: inline-block;
        background: #ef4444;
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin: 15px 0;
    }

    .cta-button {
        display: inline-block;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        text-decoration: none;
        padding: 16px 32px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        text-align: center;
        margin: 30px 0;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
    }

    .cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.6);
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
    }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="header">
            <h1>⚠️ Project Overdue</h1>
            <p>Perhatian: Project ini telah melewati batas waktu</p>
        </div>

        <div class="content">
            <div class="greeting">
                Halo {{ $user->name }}! 👋
            </div>

            <p style="color: #6b7280; margin-bottom: 25px;">
                Kami ingin mengingatkan bahwa project berikut telah melewati batas waktu:
            </p>

            <div class="project-card">
                <div class="project-name">{{ $project->name }}</div>
                <div class="overdue-badge">
                    Overdue {{ $overdueDays }} {{ $overdueDays == 1 ? 'day' : 'days' }}
                </div>
                
                <div class="project-details">
                    <p><strong>End Date:</strong> {{ $project->end_date?->format('d M Y') ?? 'N/A' }}</p>
                    <p><strong>Progress:</strong> {{ number_format($project->progress_percentage, 1) }}%</p>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{{ $projectUrl }}" class="cta-button">
                    🔍 View Project
                </a>
            </div>

            <p style="color: #6b7280; font-size: 14px; text-align: center; margin-top: 20px;">
                Silakan segera menindaklanjuti project ini untuk menghindari penundaan lebih lanjut.
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

