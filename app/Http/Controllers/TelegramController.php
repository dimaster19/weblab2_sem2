<?php

namespace App\Http\Controllers;

use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Chat;
use App\Models\Product;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Methods\Payments;

class TelegramController extends Controller
{


    protected $step_back;
    protected $category;

    public function load(Request $request, $back = false)
    {


        if (isset($request->callback_query)) {
            $chat_id = $request->callback_query['from']['id'];
            $product_id = $request->callback_query['data'];
            $message_id = $request->callback_query['message']['message_id'];
            $this->buttonAction($chat_id, $product_id);

            $button = Keyboard::make()
                ->inline()
                ->row(
                    [
                        Keyboard::inlineButton(['text' => 'Nice 👍', 'callback_data' => 'Buy'])
                    ]
                );
            Telegram::editMessageCaption( [
                'chat_id' => $chat_id,
                'reply_markup' => $button,
                'message_id' => $message_id,
                ]
            );
        }
        else {
            $chat_id = $request->message['from']['id'];

            if (isset($request->message['text'])) {
                $message = $request->message['text'];
                if ($back == true) {

                    $mess = Chat::where('chat_id', $chat_id)->get();
                    $message = $mess[0]['step_back'];
                }

                switch ($message) {

                    case '/start':
                        $this->start($chat_id);
                        break;

                    case 'Категории':
                        $this->categories($chat_id);
                        $this->step_back = '/start';
                        break;

                    case 'Ещё':
                        $cat_id = Chat::select('category')->where('chat_id', $chat_id)->get();
                        $cat_id = $cat_id[0]['category'];
                        $page = Chat::select('next_page')->where('chat_id', $chat_id)->get();
                        $page = $page[0]['next_page'];
                        if ($this->getProducts(1, $chat_id, $page) == true) {
                            $this->step_back = '/start';
                        } else {
                            $this->step_back = 'Категории';
                        }


                        $this->category = $cat_id;

                        break;

                    case 'Назад':
                        $this->load($request, true);
                        break;

                    default:
                        // Проверяю есть ли такая категория
                        $name = $request->message['text'];
                        $cat = Category::where('rus_name', $name)->first();
                        if (isset($cat)) {
                            if ($this->getProducts($cat->id,  $chat_id) == 'true') {
                                $this->step_back = '/start';
                            } else {
                                $this->step_back = 'Категории';
                            }
                            $this->category = $cat->id;
                        } else {

                            Telegram::sendMessage([
                                'chat_id' =>  $chat_id,
                                'text' => 'Я не умею обрабатывать эту команду'
                            ]);
                        }
                }
            }

            $chat = Chat::firstOrCreate(
                [
                    'chat_id' => $chat_id
                ]
            );
            $chat->step_back =  $this->step_back;
            $chat->category =  $this->category;

            $chat->save();
        }
    }


    private function start($chat_id)
    {
        $keyboard = [
            ['Категории'],
            ['Звонок'],
        ];

        $reply_markup =  Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите действие' . $this->step_back,
            'reply_markup' => $reply_markup

        ]);
    }


    private function categories($chat_id)
    {
        $category = Category::all('rus_name');
        $keyboard = array();
        foreach ($category as &$value) {
            $keyboard[] = [$value->rus_name];
        }
        $keyboard[] = ['Назад'];
        $reply_markup =  Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true

        ]);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите категорию',
            'reply_markup' =>  $reply_markup

        ]);
    }


    private function getProducts($category, $chat_id, $page = 1)
    {
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });


        $products = Product::where('category_id', $category)->orderBy('price')->paginate(5, ['*'], 'page')->withPath('');

        $last_page = $products->lastPage();

        $next_page = $products->currentPage();

        $next_page++;
        $chat = Chat::firstOrCreate(
            [
                'chat_id' => $chat_id
            ]
        );
        $chat->next_page =  $next_page;
        $chat->last_page = $last_page;

        $chat->save();

        foreach ($products as &$item) {

            $file = InputFile::create('https://gauss-shop.ru/thumb/2/KvLZtMruC4v28F6NgnYIcg/350r350/d/no-image.jpg', 'test');

            $keyboard = Keyboard::make()
                ->inline()
                ->row(
                    [
                        Keyboard::inlineButton(['text' => 'Купить ' . $item->price . '₽', 'callback_data' => $item->id])
                    ]
                );
            Telegram::sendPhoto([
                'chat_id' => $chat_id,
                'photo' =>  $file,
                'caption' => $item->name,
                'reply_markup' => $keyboard

            ]);
        }



        $keyboard = [
            ['Ещё'],
            ['Назад'],
        ];

        $reply_markup =  Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);
        if ($next_page > $last_page) {
            $this->categories($chat_id);
            return true;
        } else {
            Telegram::sendMessage([
                'chat_id' => $chat_id,
                'text' => '🛒 Страница ' . $page,
                'reply_markup' => $reply_markup

            ]);
            return false;
        }
    }



    private function buttonAction($chat_id, $product_id)
    {

        $item = Product::find($product_id);
        Telegram::sendInvoice(
            [
                'chat_id'                        => $chat_id,  // int            - Required. Unique identifier for the target chat or username of the target channel (in the format "@channelusername")
                'title'                          => $item->name,  // string         - Required. Product name, 1-32 characters
                'description'                    => $item->desc,  // string         - Required. Product description, 1-255 characters
                'payload'                        => $item->id,  // string         - Required. Bot-defined invoice payload, 1-128 bytes. This will not be displayed to the user, use for your internal processes.
                'provider_token'                 => env('PSB_PAY'),  // string         - Required. Payments provider token, obtained via Botfather
                'currency'                       => 'RUB',  // string         - Required. Three-letter ISO 4217 currency code
                'prices'                         =>
                [
                    [
                        'label' => $item->name,
                        'amount' => $item->price
                    ]
                ],  // LabeledPrice[] - Required. Price breakdown, a list of components (e.g. product price, tax, discount, delivery cost, delivery tax, bonus, etc.)
            ]

        );
    }
}
