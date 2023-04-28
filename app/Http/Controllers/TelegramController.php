<?php

namespace App\Http\Controllers;

use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\FileUpload\InputFile;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Chat;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{


    protected $step_back;
    protected $category;

    public function load(Request $request, $back = false)
    {



        if (isset($request->callback_query)) {
            $this->buttonAction($request);
            return;
        } else {
            $chat_id = $request->message['from']['id'];

            if (isset($request->message['text'])) {
                $message = $request->message['text'];
                if ($back == true) {

                    $mess = Chat::where('chat_id', $chat_id)->get();
                    $message = $mess[0]['step_back'];
                }
                Log::debug( $message);

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
                        $this->getProducts(1, $chat_id);
                        $this->step_back = 'Категории';
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
                            $this->getProducts($cat->id,  $chat_id);
                            $this->category = $cat->id;
                            $this->step_back = 'Категории';

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
            'text' => 'Выберите категорию' ,
            'reply_markup' =>  $reply_markup

        ]);
    }


    private function getProducts($category, $chat_id)
    {


        $keyboard = [
            ['Ещё'],
            ['Назад'],
        ];

        $reply_markup =  Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите действие',
            'reply_markup' => $reply_markup

        ]);

        $products = Product::where('category_id', $category)->orderBy('price')->paginate(5)->withPath('');



        $chat = Chat::firstOrCreate(
            [
                'chat_id' => $chat_id
            ]
        );
        $chat->next_page =  2;

        $chat->save();



        foreach ($products as &$item) {

            $file = InputFile::create('https://gauss-shop.ru/thumb/2/KvLZtMruC4v28F6NgnYIcg/350r350/d/no-image.jpg', 'test');
            $keyboard = Keyboard::make()
                ->inline()
                ->row(
                    [
                        Keyboard::inlineButton(['text' => 'Btn 2', 'callback_data' => $item->id])
                    ]
                );
            Telegram::sendPhoto([
                'chat_id' => $chat_id,
                'photo' =>  $file,
                'caption' => $item->name,
                'reply_markup' => $keyboard

            ]);
        }
    }



    private function buttonAction(Request $request)
    {
        return;
    }
}
