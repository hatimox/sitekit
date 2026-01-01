<?php

namespace App\Http\Controllers;

use App\Services\DocumentationService;
use Illuminate\Http\Request;

class PublicDocsController extends Controller
{
    public function __construct(
        protected DocumentationService $docs
    ) {}

    /**
     * Display the documentation index or a specific topic
     */
    public function show(Request $request, ?string $topic = null)
    {
        $topics = $this->docs->getTopics();

        // Default to getting-started if no topic specified
        $currentTopic = $topic ?? 'getting-started';

        // Check if topic exists
        if (!$this->docs->exists($currentTopic)) {
            abort(404, 'Documentation topic not found');
        }

        // Get the topic info and content
        $topicInfo = $this->docs->getTopic($currentTopic);
        $html = $this->docs->getHtml($currentTopic);

        return view('public.docs', [
            'topics' => $topics,
            'currentTopic' => $currentTopic,
            'topicInfo' => $topicInfo,
            'content' => $html,
        ]);
    }
}
