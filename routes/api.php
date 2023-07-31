<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//Route::post('user/new', 'Api\AuthApiController@new');
Route::get('role/list', 'Api\RoleApiController@viewAll');
Route::get('options', 'Api\OptionApiController@all');
Route::get('/confirm/{ref}/{token}', 'RegisteController@confirm');

Route::post('login', 'Api\AuthApiController@login');

Route::post('user/create', 'Api\UserApiController@new');
Route::post('user/producteur/create', 'Api\UserApiController@createProdAccount');

Route::post('confirm_account/{ref}','Api\AuthApiController@updatePublishAccount');

/*
 * password change
 */

Route::post('forgot_password/email','Api\UserApiController@forgotPassword');
Route::post('change_password/{ref}','Api\UserApiController@changePassword');

/***********************
 * Module Categorie
 * ********************** */
Route::get('categories/all', 'Api\CategorieApiController@allCategories');

/***********************
 * Module Unite
 * ********************** */
Route::get('unites/all', 'Api\UniteApiController@viewAll');

/***********************
 * Module Ville_surface
 * ********************** */
Route::get('ville_surface/all', 'Api\Ville_surfaceApiController@allVilleSurface');

/***********************
 * Module Quartier_surface
 * ********************** */
Route::post('quartier_surface/by/ville_surface', 'Api\Quartier_surfaceApiController@districtsByCity');

Route::get('server/date/current', 'Api\PropositionApiController@getCurrentDate');

/***********************
 * Module Produit
 * ********************** */
Route::get('direct/sale/product/list', 'Api\ProductApiController@viewAllProductsForDirectSale');

Route::get('auction/product/list', 'Api\ProductApiController@viewAllProductsForAuction');

Route::post('product/detail', 'Api\ProductApiController@viewDetailProduct');

Route::post('direct-sale/products/by/category', 'Api\ProductApiController@viewAllProductsForDirectSaleByCategory');

Route::post('auction/products/by/category', 'Api\ProductApiController@viewAllProductsForAuctionByCategory');

/***********************
 * Module Commande
 * ********************** */
Route::post('orange/payment/check', 'Api\CommandeApiController@checkCommandePayOrange');

Route::post('mtn/payment/check', 'Api\CommandeApiController@checkCommandePayMtn');

Route::get('qrcode', 'Api\CommandeApiController@TestGenerateQRCode');

/************************************************
 * Module Check payment Commande d'une proposition
 * ********************** **********************/
Route::post('auction/order/orange/payment/check', 'Api\PropositionApiController@checkCommandeAuctionPayOrange');

Route::post('auction/order/mtn/payment/check', 'Api\PropositionApiController@checkCommandeAuctionPayMtn');

Route::post('order/detail', 'Api\CommandeApiController@detailOrder');

/***********************
 * Module statut_livraison
 * ********************** */
Route::get('statut_livraison/list', 'Api\CommandeApiController@getStatutLivraisons');

/***********************
 * Module bon_livraison
 * ********************** */
Route::post('bon_livraison/detail', 'Api\BonLivraisonApiController@deliverieDetail');


/***********************
 * Module Type_livraisons
 * ********************** */
Route::get('type_livraisons/all', 'Api\TypeLivraisonApiController@viewAll');

Route::post('orders/by/type_livraison', 'Api\TypeLivraisonApiController@OrdersOfTypeLivraison');

/***********************
 * Module Quartier
 * ********************** */
Route::get('districts/all', 'Api\QuartierApiController@viewAllDistricts');

Route::post('districts/all/by/city', 'Api\QuartierApiController@districtsByCity');

Route::get('cities/all', 'Api\QuartierApiController@viewAllCities');

/***********************
 * Module cooperative
 * ********************** */
Route::get('cooperative/all', 'Api\CooperativeApiController@allCooperative');

/***********************
 * Module Auction
 * ********************** */
Route::post('auction/detail', 'Api\PropositionApiController@auctionDetail');

/***********************
 * Module surface partage
 * ********************** */
Route::get('surface/all', 'Api\SurfacePartageApiController@allSurfacePartage');

Route::post('become_partner', 'Api\PartenaireApiController@devenirPartenaire');



Route::group(['middleware'=>'auth:api'], function()
{
    Route::get('logout', 'Api\AuthApiController@logout');

    /***************************
     * Module Surface de partage
     *************************/

    Route::post('surface/create', 'Api\SurfacePartageApiController@create');
    Route::post('surface/update', 'Api\SurfacePartageApiController@update');
    Route::post('surface/delete', 'Api\SurfacePartageApiController@delete');

    /***********************
     * Module Volume
     * ********************** */
    Route::post('volume/create', 'Api\VolumeApiController@create');

    Route::post('volume/update', 'Api\VolumeApiController@update');

    Route::post('volume/delete', 'Api\VolumeApiController@delete');
    Route::get('volume/list', 'Api\VolumeApiController@viewAll');


    /***********************
     * Module Quartier_surface
     * ********************** */
    Route::post('quartier_surface/create', 'Api\Quartier_surfaceApiController@create');
    Route::post('quartier_surface/update', 'Api\Quartier_surfaceApiController@update');
    Route::post('quartier_surface/delete', 'Api\Quartier_surfaceApiController@delete');

    /***********************
     * Module Categorie
     * ********************** */
    Route::post('categorie/create', 'Api\CategorieApiController@create');

    Route::post('categorie/update', 'Api\CategorieApiController@update');

    Route::post('categorie/delete', 'Api\CategorieApiController@delete');

    /***********************
     * Module Produit
     * ********************** */

    Route::post('product/picture/add', 'Api\AuthApiController@addPictureProduct');

    Route::post('product/create', 'Api\ProductApiController@create');

    Route::post('product/delete', 'Api\ProductApiController@delete');

    Route::post('product/update', 'Api\ProductApiController@update');

    Route::get('user/producer/products/all', 'Api\ProductApiController@viewAllProductsOfProducer');

    Route::get('user/gestionnaire-cooperative/products/all', 'Api\ProductApiController@viewAllProductsOfCooperative');

    Route::post('user/gestionnaire-cooperative/product/reciept/confirm', 'Api\ProductApiController@confirmRecieptProduct');

    /***********************
     * Module Unite
     * ********************** */
    Route::post('unite/create', 'Api\UniteApiController@create');

    Route::post('unite/update', 'Api\UniteApiController@update');

    Route::post('unite/delete', 'Api\UniteApiController@delete');


    /***********************
     * Module Quartier
     * ********************** */
    Route::post('district/create', 'Api\QuartierApiController@create');

    Route::post('district/update', 'Api\QuartierApiController@update');

    Route::post('district/delete', 'Api\QuartierApiController@delete');

    /***********************
     * Module Type_livraisons
     * ********************** */
    Route::post('type_livraison/create', 'Api\TypeLivraisonApiController@create');

    Route::post('type_livraison/update', 'Api\TypeLivraisonApiController@update');

    Route::post('type_livraison/delete', 'Api\TypeLivraisonApiController@delete');


    /***********************
     * Module user
     * ********************** */
    Route::post('user/admin/create', 'Api\UserApiController@new');
    Route::get('user/list', 'Api\UserApiController@ListeUser');
    Route::get('user/client/list', 'Api\UserApiController@listeClient');
    Route::post('user/detail', 'Api\UserApiController@detailUser');
    Route::post('user/cooperative', 'Api\UserApiController@getUserByCooperative');
    Route::get('user/coursier/list', 'Api\UserApiController@listeCoursier');
    Route::post('user/account/update', 'Api\UserApiController@update');
    Route::post('user/delete', 'Api\UserApiController@delete');



    /***********************
     * Module Commande
     * ********************** */
    Route::post('order/create', 'Api\CommandeApiController@create');

    Route::get('customer/orders/list', 'Api\CommandeApiController@OrdersOfCustomer');

    Route::get('orders/all', 'Api\CommandeApiController@allOrders');

    /***********************
     * Module bon_commande
     * ********************** */
    Route::post('boncommande/create', 'Api\BonCommandeApiController@createBonCommande');

    Route::get('boncommande/list', 'Api\BonCommandeApiController@getBonCommandeList');

    Route::get('boncommande/detail/cooperative', 'Api\BonCommandeApiController@cooperativeBonCommande');

    Route::post('boncommande/assign', 'Api\BonCommandeApiController@assignToCoursier');

    Route::post('detail/boncommande/check', 'Api\BonCommandeApiController@checkDetailBonCommande');

    Route::post('detail/boncommande/sign', 'Api\BonCommandeApiController@signDetailBonCmd');//signature du detail bon de commande par le gestionnaire de la cooperative

    Route::post('boncommande/sign', 'Api\BonCommandeApiController@signBonCommande');//signature du bon de commande par le gestionnaire de la surface de partage

    Route::get('boncommande/all/assign/coursier', 'Api\BonCommandeApiController@bonCommandeByCoursier');

    Route::post('boncommande/detail', 'Api\BonCommandeApiController@detailBonCommande');

    /***********************
     * Module bon_livraison
     * ********************** */
    Route::post('bon-livraison/create', 'Api\BonLivraisonApiController@create');

    Route::post('bon-livraison/cancel', 'Api\BonLivraisonApiController@cancel');

    Route::post('customer/order/sign', 'Api\BonLivraisonApiController@customerOrderSign');

    Route::post('user/deliver/bon-livraison/sign', 'Api\BonLivraisonApiController@signOfLivreur');

    Route::get('bon-livraisons/all', 'Api\BonLivraisonApiController@allDeliveries');

    Route::get('user/deliver/bon-livraisons/all', 'Api\BonLivraisonApiController@allLivraisonsOfLivreur');


    /***********************
     * Module cooperative
     * ********************** */
    Route::post('cooperative/create', 'Api\CooperativeApiController@create');

    Route::post('cooperative/update', 'Api\CooperativeApiController@update');

    Route::post('cooperative/delete', 'Api\CooperativeApiController@delete');

    Route::get('user/producteur/list', 'Api\UserApiController@listeProducteurs');

    Route::get('user/livreur/list', 'Api\UserApiController@listeLivreurs');

    /***********************
     * Module Auction
     * ********************** */
    Route::post('auction/create', 'Api\PropositionApiController@createAuction');

    Route::post('auction/order/payment', 'Api\PropositionApiController@orderPayment');

    //customer : Afficher la liste des propositions d'un client sur un produit
    Route::post('customer/auction/list/by/product', 'Api\PropositionApiController@listAuctionsOfCustomer');

    //Admin|Gestionnaire-surface : Afficher la liste des propositions sur un produit
    Route::post('auctions/list/by/product', 'Api\PropositionApiController@listAuctions');

    //ajout du produit gagné dans la commande
    Route::post('auctions/product/win', 'Api\PropositionApiController@auctionStop');

    //Afficher la liste des commandes par rapport aux enchères d'un client
    Route::get('customer/auctions/orders/list', 'Api\PropositionApiController@orderAuctionOfCustomer');

    //Afficher les commandes par rapport aux enchères acceptées concernant les clients
    Route::get('auctions/orders/list', 'Api\PropositionApiController@allOrdersAuctions');

});
