<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'question' => 'How does the four-phase workflow work?',
                'answer' => 'Selection (clients pick favorites), Proofing (feedback and revisions), Collection (final delivery), and Raw files (store and deliver originals with optional PIN). One link per project—no client account required.',
            ],
            [
                'question' => 'Do my clients need to create an account?',
                'answer' => 'No. You share a link; clients view, comment, and download from their browser. No sign-up or app required.',
            ],
            [
                'question' => "I'm tired of juggling WeTransfer, email threads, and spreadsheets. How does Memora help?",
                'answer' => 'Memora replaces all those tools with one workflow. Upload once, share one link. Clients pick favorites, leave feedback, and download—all in one place. No more email tag or lost files.',
            ],
            [
                'question' => 'Can I protect my galleries with passwords or watermarks?',
                'answer' => 'Yes. You can set gallery passwords, apply watermarks, and control download access. Available on Standard and higher plans.',
            ],
            [
                'question' => 'How do I handle client feedback without losing track of emails?',
                'answer' => 'All comments and revision requests live in the proofing phase, attached to specific images. You get one clear list of what needs editing—no digging through inboxes or chat threads.',
            ],
            [
                'question' => 'Can I brand my galleries with my logo and colors?',
                'answer' => 'Yes. Professional plans include full branding customization. Your logo, colors, and style appear on every client page, so you look polished from first share to final delivery.',
            ],
            [
                'question' => "What happens to my RAW files? Can clients access them?",
                'answer' => "RAW files are stored separately in the Raw files phase. You control what's shared and when. Clients only see what you've explicitly shared, with optional PIN protection for extra security.",
            ],
            [
                'question' => 'How do I deliver final images to clients?',
                'answer' => 'Create a Collection—your delivery gallery. Share one link; clients view, download high-resolution files, or order prints. No separate delivery service needed.',
            ],
            [
                'question' => 'What if a client wants revisions? How does that work?',
                'answer' => 'Clients request revisions in the proofing phase. You upload new versions, and they can approve or request more changes. Full history is kept so nothing gets lost.',
            ],
            [
                'question' => 'Can I limit how many photos clients can select?',
                'answer' => 'Yes. Set selection limits per gallery (e.g., "pick up to 50"). This helps manage expectations and keeps your workflow organized.',
            ],
            [
                'question' => 'What file formats do you support?',
                'answer' => 'We support JPEG, PNG, and all major RAW formats (Canon, Nikon, Sony, Fujifilm, etc.). You can upload both processed and RAW files, linking them together for easy reference.',
            ],
            [
                'question' => 'How much storage do I get?',
                'answer' => 'Storage limits vary by plan. Starter includes enough for most creatives; Professional offers more storage and higher limits. See the pricing page for specific amounts.',
            ],
            [
                'question' => 'Can I use this for multiple clients at once?',
                'answer' => 'Yes. Each project gets its own link and workflow. Manage multiple shoots simultaneously—everything stays organized and separate.',
            ],
            [
                'question' => "What if my client isn't tech-savvy? Will they be able to use this?",
                'answer' => 'Absolutely. Clients just click a link and view photos in their browser. No app, no account, no learning curve. It works on phones, tablets, and computers.',
            ],
            [
                'question' => 'How do I know when clients have viewed or downloaded photos?',
                'answer' => 'You get notifications when clients view galleries, make selections, leave comments, or download files. Stay in sync without constantly checking.',
            ],
            [
                'question' => 'Can I customize the gallery layout and design?',
                'answer' => 'Yes. Choose from different layouts, customize colors and branding, and control how photos are displayed. Make it match your style.',
            ],
            [
                'question' => 'What happens if I need to update or replace a photo after sharing?',
                'answer' => 'You can update photos anytime. Upload new versions, and clients see the latest files. Previous versions are kept in history for reference.',
            ],
            [
                'question' => 'Is there a limit on how many projects I can have?',
                'answer' => 'Project limits vary by plan. Starter includes enough for most creatives; Professional offers higher limits. See pricing for details.',
            ],
            [
                'question' => 'Can I download all my files if I want to switch platforms?',
                'answer' => 'Yes. You can export and download all your files at any time. Your data belongs to you.',
            ],
            [
                'question' => 'What plans do you offer?',
                'answer' => 'We offer Starter and Professional plans. Each includes galleries, proofing, and delivery; Professional adds branding, Studio Manager, and more. See the pricing page for details.',
            ],
            [
                'question' => 'Is my data secure?',
                'answer' => 'Yes. We use industry-standard encryption and security practices. Your files and client data are stored securely. We never share your work without permission.',
            ],
        ];

        foreach ($items as $i => $item) {
            Faq::firstOrCreate(
                ['question' => $item['question']],
                [
                    'answer' => $item['answer'],
                    'sort_order' => $i,
                    'is_published' => true,
                ]
            );
        }
    }
}
