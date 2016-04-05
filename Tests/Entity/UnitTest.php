<?php

namespace Plugin\ProductReview\Tests\Entity;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Plugin\ProductReview\Entity\ProductReview;

class UnitTest extends AbstractAdminWebTestCase
{
    protected  $TestReview;

    public function setup()
    {
        parent::setUp();
        $faker = $this->getFaker();
        $this->TestReview = $this->createReview(0, $faker->word, 1);
    }
    /**
     * レビュー検索画面のルーティング
     */
    public function test_routing_review_search()
    {
        $this->client->request(
            'GET',
            $this->app->url('admin_product_review')
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * レビュー編集画面のルーティング
     */
    public function test_routing_review_edit()
    {

        $test_review_id = $this->get_test_review_id($this->TestReview);
        $crawler = $this->client->request('GET',
            $this->app->url('admin_product_review_edit', array('id' => $test_review_id))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }


    /**
     * レビュー編集画面のデータ編集
     */
    public function test_review_edit()
    {
        $test_review_id = $this->get_test_review_id($this->TestReview);
        $formData = $this->reviewAdminEditForm($test_review_id, 1);
        $this->assertTrue($this->client->getResponse()->isRedirect($this->app->url('admin_product_review')));
        $this->expected = $formData['reviewer_name'];
        $this->actual = $this->TestReview->getReviewerName();
        $this->verify();
    }

    /**
     * レビュー公開チェック
     */
    public function test_review_public()
    {
        try{
            $test_review_id = $this->get_test_review_id($this->TestReview);
            $this->reviewAdminEditForm($test_review_id, 1);
            var_dump($this->TestReview->getProduct()->getId());
            $crawler = $this->getProductDetailCrawler($this->TestReview->getProduct()->getId());
            die();
            $crawler->filter('.review_list')->text();
            $this->assertTrue(true);
        }catch(\InvalidArgumentException $e){
            $this->assertTrue(false);
        }
    }

    /**
     * レビュー非公開チェック
     */
    public function test_review_not_public()
    {
        try{
            $test_review_id = $this->get_test_review_id($this->TestReview);
            $this->reviewAdminEditForm($test_review_id, 2);
            $crawler = $this->getProductDetailCrawler($this->TestReview->getProduct()->getId());
            $crawler->filter('.review_list')->text();
            $this->assertTrue(false);
        }catch(\InvalidArgumentException $e){
            $this->assertTrue(true);
        }
    }

    /**
     * 削除チェック
     */
    public function test_review_delete()
    {
        $faker = $this->getFaker();
        $TestReviewDel = $this->createReview(0, $faker->word, 1);
        $repos = $this->app['eccube.plugin.product_review.repository.product_review'];
        $status = $repos->delete($TestReviewDel);
        $this->assertTrue($status);
    }

    /**
     * 商品レビュー入力画面
     */
    public function test_review_display()
    {
        $product_id = $this->createProduct()->getId();
        $crawler = $this->client->request('GET',
            $this->app->url('products_detail_review', array('id' => $product_id))
        );
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * 商品レビュー確認画面
     */
    public function test_review_confirm()
    {
        $Product = $this->createProduct();
        $this->reviewEditForm($Product, 'confirm');
        $this->reviewEditForm($Product, 'complete');
        $this->assertTrue($this->client->getResponse()->isRedirect($this->app->url('products_detail_review_complete', array('id' => $Product->getId()))));
    }

    /**
     * 商品レビューAdmin確認画面
     */
    public function reviewAdminEditForm($test_review_id, $status)
    {
        $formData = $this->createReviewFormData($status);
        $ProductReview = $this->app['eccube.plugin.product_review.repository.product_review']->find($test_review_id);
        $crawler = $this->client->request(
            'POST',
            $this->app->url('admin_product_review_edit', array('id' => $test_review_id)),
            array(
                'admin_product_review' => $formData,
                'Product' => $ProductReview->getProduct()
            )
        );
        return $formData;
    }

    /**
     * 商品レビューフロント確認画面
     */
    public function reviewEditForm($Product, $mode)
    {
        $formData = $this->createReviewFormDataFrontend();
        $crawler = $this->client->request(
            'POST',
            $this->app->url('products_detail_review', array('id' => $Product->getId())),
            array(
                'mode' => $mode,
                'product_review' => $formData,
                'Product' => $Product
            )
        );
    }

    /**
     * 商品レビューのIDを取得
     */
    public function get_test_review_id($TestReview)
    {
        $test_review_id = $this->app['eccube.plugin.product_review.repository.product_review']
            ->findOneBy(array(
                'reviewer_name' => $TestReview->getReviewerName()
            ))
            ->getId();
        return $test_review_id;
    }
    /**
     * レビューフォームの作成
     */
    public function createReviewFormData($status)
    {
        $faker = $this->getFaker();
        $form = array(
            '_token' => 'dummy',
            'reviewer_name' => $faker->word,
            'reviewer_url' => $faker->url,
            'sex' => 1,
            'recommend_level' => 5,
            'title' => $faker->word,
            'comment' => $faker->word,
            'status' => $status
        );
        return $form;
    }

    /**
     * フロントレビューフォームの作成(Status削除)
     */
    public function createReviewFormDataFrontend()
    {
        $form = $this->createReviewFormData(1);
        unset($form['status']);
        return $form;
    }


    /**
     * 商品詳細のHTMLの取得
     */
    public function getProductDetailCrawler($id){
        $crawler = $this->client->request('GET', $this->app->url('product_detail', array('id' => $id)));
        return $crawler;
    }


    /**
     * レビュー作成
     */
    public function createReview($delFlg, $reviewer, $disp)
    {
        $faker = $this->getFaker();
        $Review = new ProductReview();
        //新しい商品の作成
        $Product = $this->createProduct();
        $Disp = $this->app['eccube.repository.master.disp']->find($disp);
        $Review
            ->setComment($faker->word)
            ->setDelFlg($delFlg)
            ->setReviewerName($reviewer)
            ->setRecommendLevel(5)
            ->setTitle($faker->word)
            ->setProduct($Product)
            ->setStatus($Disp);
        $this->app['orm.em']->persist($Review);
        $this->app['orm.em']->flush();
        return $Review;
    }

}
