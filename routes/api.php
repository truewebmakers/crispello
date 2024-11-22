<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DeliveryPersonController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\HomeSliderController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OTPController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductSizeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\{CustomizationController, ReferralCampaignController,DeliveryPartnerFareSettingController};


use Illuminate\Support\Facades\Route;


Route::middleware(['auth:admin,user'])->group(function () {
    Route::get('slider/getsliders', [HomeSliderController::class, 'getSliders']);
    Route::get('feedback/getall', [FeedbackController::class, 'getAllFeedbacks']);
    Route::post('feedback/delete', [FeedbackController::class, 'deleteFeedback']);
    Route::get('video/getall', [VideoController::class, 'getAllVideos']);
});

Route::middleware(['auth:admin,delivery_driver'])->group(function () {
    Route::post('admin/order/status/update', [OrderController::class, 'updateStatusAdmin']);
});

Route::post('user/sendotp', [OTPController::class, 'sendOTP']);

//user login
Route::post('user/verifyotp', [UserController::class, 'verifyOTP']);

//onboarding for customer
Route::get('user/onboarding/getonboardings', [OnboardingController::class, 'getOnboardings']);

Route::prefix('user')->middleware(['auth:user', 'scope:user'])->group(function () {
    //home api
    Route::get('home/getalldetails', [ProductController::class, 'getAllHomeDetails']);

    Route::get('restaurant/get', [AdminController::class, 'getRestaurantDetailsUser']);
    //product category
    Route::get('productcategory/getallcategories', [ProductCategoryController::class, 'getAllProductCategoriesCustomer']);
    //product
    Route::get('product/getproductsbycategory', [ProductController::class, 'getAllProductByCategoryCustomer']);
    Route::get('product/getbestsellers', [ProductController::class, 'getBestSeller']);
    Route::get('product/getrecommendeds', [ProductController::class, 'getRecommendeds']);
    Route::get('product/search', [ProductController::class, 'searchProduct']);

    //profile
    Route::post('profile/update', [UserController::class, 'updateProfile']);
    Route::get('profile/get', [UserController::class, 'getProfile']);

    //address
    Route::post('address/add', [AddressController::class, 'addAddress']);
    Route::post('address/update', [AddressController::class, 'updateAddress']);
    Route::post('address/delete', [AddressController::class, 'deleteAddress']);
    Route::get('address/getall', [AddressController::class, 'getAllAddresses']);

    //combo
    Route::get('combo/all', [ComboController::class, 'getAllCombosCustomer']);

    //cart
    Route::post('cart/product/add', [CartController::class, 'addToCart']);
    Route::post('cart/product/remove', [CartController::class, 'removeFromCart']);
    Route::post('cart/update', [CartController::class, 'updateCart']);
    Route::get('cart/get', [CartController::class, 'getCart']);

    //coupon
    Route::get('coupon/all', [CouponController::class, 'getCouponsCustomer']);
    Route::post('coupon/apply', [CouponController::class, 'applyCoupon']);
    Route::post('coupon/remove', [CouponController::class, 'removeAppliedCoupon']);

    //order
    Route::post('order/placeorder', [OrderController::class, 'placeOrder']);
    Route::get('order/history', [OrderController::class, 'getOrderHistory']);
    Route::post('order/status/update', [OrderController::class, 'updateStatusCustomer']);
    Route::post('order/reorder', [OrderController::class, 'reorder']);

    //feedback
    Route::post('feedback/add', [FeedbackController::class, 'addFeedback']);
    Route::post('feedback/update', [FeedbackController::class, 'updateFeedback']);

    //logout
    Route::post('logout', [UserController::class, 'userLogout']);

    //notification
    Route::get('notification/getall', [NotificationController::class, 'getNotifications']);
    Route::post('notification/read', [NotificationController::class, 'readNotification']);



    Route::post('referral/delete/{id}', [ReferralCampaignController::class, 'delete']);
    Route::post('referral/log/store', [ReferralCampaignController::class, 'referralLog']);
    Route::post('referral/log/update/{id}', [ReferralCampaignController::class, 'referralLogUpdateStauts']);

    Route::post('referral/log/get', [ReferralCampaignController::class, 'referralget']);

    Route::get('referral/get/code', [ReferralCampaignController::class, 'fetchDataWithCode']);
    Route::post('referral/create/code', [ReferralCampaignController::class, 'craeteUserCode']);

    Route::get('referral/get/withoutcode', [ReferralCampaignController::class, 'fetchDataWithOutCode']);

    // customer Wallet
    Route::post('customer/wallet/store', [ReferralCampaignController::class, 'CustomerWallet']);
    Route::get('customer/wallet/get/{user_id}', [ReferralCampaignController::class, 'CustomerWalletFetch']);

    // delivery partner log
    Route::post('delivery/fare/log', [DeliveryPartnerFareSettingController::class, 'DeliveryPartnerStorelogs']);
    Route::get('delivery/fare/get/{partner_id}', [DeliveryPartnerFareSettingController::class, 'DeliveryPartnerStorelogsget']);

});

//admin
Route::post('admin/login', [AdminController::class, 'adminLogin']);
Route::post('admin/password/change', [AdminController::class, 'changePassword']);
// Route::post('admin/register', [AdminController::class, 'adminRegistration']);
Route::post('admin/logout', [AdminController::class, 'adminLogout']);

Route::prefix('admin')->middleware(['auth:admin', 'scope:admin'])->group(function () {
    //sliders
    Route::post('slider/add', [HomeSliderController::class, 'addSlider']);
    Route::post('slider/update', [HomeSliderController::class, 'updateSlider']);
    Route::post('slider/delete', [HomeSliderController::class, 'deleteSlider']);

    //onboarding
    Route::get('onboarding/getonboardings', [OnboardingController::class, 'getOnboardings']);
    Route::post('onboarding/add', [OnboardingController::class, 'addOnboarding']);
    Route::post('onboarding/update', [OnboardingController::class, 'updateOnboarding']);
    Route::post('onboarding/delete', [OnboardingController::class, 'deleteOnboarding']);

    //product category
    Route::post('productcategory/add', [ProductCategoryController::class, 'addProductCategory']);
    Route::post('productcategory/update', [ProductCategoryController::class, 'updateProductCategory']);
    Route::post('productcategory/delete', [ProductCategoryController::class, 'deleteProductCategory']);
    Route::get('productcategory/getallcategories', [ProductCategoryController::class, 'getAllProductCategoriesAdmin']);

    //product
    Route::post('product/add', [ProductController::class, 'addProduct']);
    Route::post('product/update', [ProductController::class, 'updateProduct']);
    Route::post('product/delete', [ProductController::class, 'deleteProduct']);
    Route::get('product/getproductsbycategory', [ProductController::class, 'getAllProductByCategoryAdmin']);
    Route::get('product/getallproducts', [ProductController::class, 'getallproductsrelated']);

    //product size
    Route::post('product/size/add', [ProductSizeController::class, 'addProductSize']);
    Route::post('product/size/update', [ProductSizeController::class, 'updateProductSize']);
    Route::post('product/size/delete', [ProductSizeController::class, 'deleteProductSize']);

    //profile
    Route::post('profile/update', [AdminController::class, 'updateProfile']);
    Route::get('profile/get', [AdminController::class, 'getProfile']);

    //userlist
    Route::get('getallusers', [UserController::class, 'getAllUsers']);

    //combo
    Route::post('combo/add', [ComboController::class, 'addCombo']);
    Route::post('combo/update', [ComboController::class, 'updateCombo']);
    Route::post('combo/delete', [ComboController::class, 'deleteCombo']);
    Route::get('combo/all', [ComboController::class, 'getAllCombosAdmin']);
    Route::get('combo/product/getall', [ComboController::class, 'getProductsForCombo']);

    //discount coupon
    Route::post('coupon/add', [CouponController::class, 'addCoupon']);
    Route::post('coupon/update', [CouponController::class, 'updateCoupon']);
    Route::post('coupon/delete', [CouponController::class, 'deleteCoupon']);
    Route::get('coupon/all', [CouponController::class, 'getCouponsAdmin']);

    //order
    Route::get('order/all', [OrderController::class, 'getAllOrdersAdmin']);
    // Route::post('order/status/update', [OrderController::class, 'updateStatusAdmin']);

    //feedback
    Route::post('feedback/reply/add', [FeedbackController::class, 'giveReply']);
    Route::post('feedback/reply/delete', [FeedbackController::class, 'removeReply']);

    //video
    Route::post('video/add', [VideoController::class, 'addVideo']);
    Route::post('video/update', [VideoController::class, 'updateVideo']);
    Route::post('video/delete', [VideoController::class, 'deleteVideo']);
    Route::get('productcategory/get', [ProductCategoryController::class, 'getAllProductCategoriesCustomer']);

    //notification
    Route::post('notification/broadcast', [NotificationController::class, 'broadcastNotification']);

    //delivery
    Route::get('delivery/getalldrivers', [DeliveryPersonController::class, 'getDriverList']);
    Route::post('delivery/request/send', [OrderController::class, 'sendDeliveryRequest']);
    Route::post('delivery/request/cancel', [OrderController::class, 'cancelDeliveryRequest']);

    // Delivery fare setting
    Route::post('fare/setting/add', [DeliveryPartnerFareSettingController::class, 'store']);
    Route::post('fare/setting/update/{id}', [DeliveryPartnerFareSettingController::class, 'update']);
    Route::get('fare/setting/get', [DeliveryPartnerFareSettingController::class, 'index']);


    // Referral

    Route::post('referral/store', [ReferralCampaignController::class, 'store']);
    Route::post('referral/update/{id}', [ReferralCampaignController::class, 'update']);
    Route::get('referral/get', [ReferralCampaignController::class, 'index']);

    Route::post('referral/delete/{id}', [ReferralCampaignController::class, 'delete']);
    Route::post('referral/log/store', [ReferralCampaignController::class, 'referralLog']);

    Route::get('referral/get/code', [ReferralCampaignController::class, 'fetchDataWithCode']);



    // customer Wallet
    Route::post('customer/wallet/store', [ReferralCampaignController::class, 'CustomerWallet']);
    Route::get('customer/wallet/get/{user_id}', [ReferralCampaignController::class, 'CustomerWalletFetch']);

    //customization
    Route::post('customization/add', [CustomizationController::class, 'addCustomization']);
    Route::post('customization/update', [CustomizationController::class, 'updateCustomization']);
    Route::post('customization/delete', [CustomizationController::class, 'deleteCustomization']);
    Route::get('customization/getall', [CustomizationController::class, 'getAllCustomizationsAdmin']);

});

//delivery person
Route::post('delivery/login', [DeliveryPersonController::class, 'deliveryPersonLogin']);
Route::prefix('delivery')->middleware(['auth:delivery_driver', 'scope:delivery_driver'])->group(function () {

    //profile
    Route::get('profile/get', [DeliveryPersonController::class, 'getProfile']);
    Route::post('profile/update', [DeliveryPersonController::class, 'updateProfile']);
    Route::post('user/location/update', [DeliveryPersonController::class, 'updateLocation']);

    //delivery request
    Route::get('request/getall', [DeliveryController::class, 'getAllDeliveryRequest']);
    Route::get('getall', [DeliveryController::class, 'getAllCompletedAndRejectedDeliveries']);
    Route::get('request/get',[DeliveryController::class,'getDeliveryRequest']);
    Route::post('request/status/change',[DeliveryController::class,'changeDeliveryRequestStatus']);

    Route::get('log/getall', [DeliveryPartnerFareSettingController::class, 'DeliveryPartnerStorelogsget']);
    Route::get('log/store', [DeliveryPartnerFareSettingController::class, 'DeliveryPartnerStorelogs']);
    Route::get('log/update/{id}', [DeliveryPartnerFareSettingController::class, 'UpdateLogs']);



});
