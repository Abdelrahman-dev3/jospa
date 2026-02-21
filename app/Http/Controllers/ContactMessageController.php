<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Services\TaqnyatSmsService;

class ContactMessageController extends Controller
{

public function index(Request $request)
{
    $sort = $request->get('srt', 'ASC');
    $sort = in_array($sort, ['ASC', 'DESC']) ? $sort : 'ASC';

    $messages = ContactMessage::orderBy('created_at', $sort)->get();

    return view('home.contact', compact('messages'));
}


public function store(Request $request)
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => [
            'nullable',
            'string',
            'max:20',
            function ($attribute, $value, $fail) {
                if (!preg_match('/^(?:\+9665|9665|05|5)\d{8}$/', $value)) {
                    $fail(__('messages.invalid_saudi_phone'));
                }
            },
        ],
        'message' => 'required|string',
    ]);

    $data['accepted_terms'] = $request->has('accepted_terms') ? 1 : 0;

    ContactMessage::create($data);

    return back()->with('success', __('messages.message_sent_success'));
}


public function reply(Request $request, $id)
{
    $request->validate([
        'message' => 'required|string',
    ]);

    // SMS API
    $contact = ContactMessage::findOrFail($id);
    
    $smsService = new TaqnyatSmsService();
    $phone = $smsService->validatePhoneNumber($contact->phone);
    
    if (!$phone) {
        return redirect()->back()->with('error', __('messages.invalid_phone'));
    }
    
    if ($smsService->sendSms($phone, $request->message)) {
        return back()->with('success', __('messages.message_sent_success'));
    } else {
        return back()->with('error', __('messages.message_send_failed'));
    }

    
}
    
public function bulkAction(Request $request)
{
    $action = $request->input('action_type');
    $masseges_ids = $request->input('masseges_ids', []);
    if (empty($masseges_ids)) {
        return back()->with('error', __('messages.select_at_least_one'));
    }

    if ($action == 'delete') {
        ContactMessage::whereIn('id', $masseges_ids)->delete();
        return back()->with('success', __('messages.messages_deleted_success'));
    }

    return back()->with('error', __('messages.no_valid_action'));
}



}
