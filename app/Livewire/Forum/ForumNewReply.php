<?php

namespace App\Livewire\Forum;

use App\Models\Reply;
use App\Models\Thread;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;

class ForumNewReply extends Component
{
    public Thread $thread;
    public $body;

    protected $rules = [
        'body' => 'required|string|min:3|max:3000',
    ];

    public function submit()
    {
        $this->validate();
        $lockKey = auth()->user()->id.':forum:post';
        $lockAcquired = Redis::set($lockKey, 'locked', 'NX', 'EX', 5);
        if (!$lockAcquired) {
            return $this->dispatch('toast:error', 'You are posting too fast. Please wait a few seconds before posting again.');
        }

        DB::beginTransaction();
        try {
            $reply = new Reply();
            $reply->user_id = auth()->user()->id;
            $reply->topic_id = $this->thread->topic->id;
            $reply->thread_id = $this->thread->id;
            $reply->body = $this->body;
            $reply->save();
            $thread = $this->thread;
            $thread->last_reply = Carbon::now();
            $thread->save();
            DB::commit();
            $this->body = '';
            // Optionally, you can add a success message or any other response
            return redirect()->route('forum.thread', $this->thread)->with('success', 'Reply posted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Redis::del($lockKey);
            return $this->dispatch('toast:error', 'An error occurred while posting your reply. Please try again.');
        }
    }

    public function mount(Thread $thread)
    {
        $this->thread = $thread;

        if($this->thread->locked > 0 && auth()->user()->power <= 0)
        {
            return redirect()->route('forum.thread', $this->thread)->with('error', 'You do not have permission to reply to this thread.');
        }
    }

    public function render()
    {
        return view('livewire.forum.forum-new-reply', [
            'thread' => $this->thread,
        ]);
    }
}