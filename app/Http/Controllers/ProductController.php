<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::all();
        return view('product.index', compact('products'));
    }

    public function checkout()
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));

        $lineItems = [];
        $products = Product::all();
        $totalPrice = 0;

        foreach ($products as $product) {
            $totalPrice += $product->price;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->name,
                        // 'images' => [$product->image],
                    ],
                    'unit_amount' => $product->price * 100,
                ],
                'quantity' => 1,


            ];
        }

        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true) . "?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel', [], true),
        ]);

        $order = new Order();
        $order->status = 'unpaid';
        $order->total_price = $totalPrice;
        $order->session_id = $checkout_session->id;
        $order->save();

        return redirect($checkout_session->url);
    }

    public function success(Request $request)
    {
        // $customer = null;
        // // $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));
        // \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
        // try {
        //     $sessionId = $request->get('session_id');
        //     $session = \Stripe\Checkout\Session::retrieve($sessionId);
        //     if (!$sessionId) {
        //         throw new NotFoundHttpException;
        //     }
        //     $customer = \Stripe\Customer::retrieve($session->customer);
        //     $order = Order::where('session_id', $session->id)->where('status', 'unpaid')->first();
        //     if (!$order) {
        //         throw new NotFoundHttpException;
        //     }
        //     $order->status = 'paid';
        //     $order->save();
        //     return view('product.checkout-success', compact('customer'));
        // } catch (\Throwable $th) {
        //     throw new NotFoundHttpException;
        // }
        return view('product.checkout-success');
    }

    public function cancel()
    {
    }

    public function webhook()
    {

        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response('', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response('', 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        return response('', 200);
    }
}
