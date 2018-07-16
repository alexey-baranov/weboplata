<?php
/**
 * Created by PhpStorm.
 * User: alexey2baranov
 * Date: 6/30/16
 * Time: 6:56 PM
 */

namespace Weboplata;


class WeboplataTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var Weboplata
   */
  protected $object;

  /**
   * Sets up the fixture, for example, opens a network connection.
   * This method is called before a test is executed.
   */
  protected function setUp()
  {
    $this->object = new \Zend_Rest_Client("http://localhost/weboplata/public/2.0.php");
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  protected function tearDown()
  {
  }

  /**
   */
  public function testgetAccountNumberByPhoneNumberLocal()
  {
    $weboplata2 = new Weboplata2();

    $result = $weboplata2->getAccountNumberByPhoneNumber("555555");
    $this->assertEquals("А2756-Т", $result);
  }

  /**
   */
  public function testgetAccountNumberByPhoneNumber()
  {
    /** @var \Zend_Rest_Client_Result $result */
    $result = $this->object->getAccountNumberByPhoneNumber("555555")
      ->get();

    $this->assertEquals("А2756-Т", $result->__toString());
  }


  public function testcheckLocal()
  {
    $weboplata2 = new Weboplata2();

    $result = $weboplata2->check("789-И");
    $this->assertEquals($result->result->__toString(), "0");

    $result = $weboplata2->check("555555");
    $this->assertEquals($result->result->__toString(), "0");

    //ошибки
    $result = $weboplata2->check("789999-И");
    $this->assertEquals($result->result->__toString(), "5");

    $result = $weboplata2->check("789");
    $this->assertEquals($result->result->__toString(), "5");
  }

  public function testcheck()
  {
    /** @var \Zend_Rest_Client_Result $result */
    $result = $this->object->check("789-И")->get();
    $this->assertEquals($result->result->__toString(), "0");

    $result = $this->object->check("555555")->get();
    $this->assertEquals($result->result->__toString(), "0");

    //ошибки
    $result = $this->object->check("789999-И")->get();
    $this->assertEquals($result->result->__toString(), "5");

    $result = $this->object->check("789")->get();
    $this->assertEquals($result->result->__toString(), "5");
  }

//  public function testAddRepeateExternalPaymentId()
//  {
//    /** @var \Zend_Rest_Client_Result $result */
//    $result = $this->object->add("789-И", 5, (new \DateTime())->format('Y-m-d\TH:i:s'), "Терминал Офис", 8, 1427962943)
//      ->get();
//
//    $this->assertFalse($result->isSuccess());
//    $this->assertEquals("Платеж с таким вшеншим идентификатором уже существует", $result->message());
//  }
/* этот тест нужен только на время разраба. потом он коментится чтобы не нарушить уникальность номера платежа в онлайн тесте
  public function testPayLocal()
  {
    $txn_id= (new \DateTime())->getTimestamp();
    $result = (new Weboplata2())->pay("789-И", 0.1, (new \DateTime)->format('YmdHis'), $txn_id);
    $this->assertEquals($result->result->__toString(), "0");
    $this->assertEquals($result->osmp_txn_id->__toString(), $txn_id);

    $txn_id++;
    $result = (new Weboplata2())->pay("555555", 0.1, (new \DateTime)->format('YmdHis'), $txn_id);
    $this->assertEquals($result->result->__toString(), "0");
    $this->assertEquals($result->osmp_txn_id->__toString(), $txn_id);
  }*/

  public function testPay()
  {
    $txn_id= (new \DateTime())->getTimestamp();
    $result = $this->object->pay("99999-И", 0.1, (new \DateTime)->format('YmdHis'), $txn_id)->get();
    $this->assertEquals($result->result->__toString(), "0");
    $this->assertEquals($result->osmp_txn_id->__toString(), $txn_id);
    $this->assertEquals($result->sum->__toString(), "0.1");

    $txn_id++;
    $result = $this->object->pay("99999-И", 0.1, (new \DateTime)->format('YmdHis'), $txn_id)->get();
    $this->assertEquals($result->result->__toString(), "0");
    $this->assertEquals($result->osmp_txn_id->__toString(), $txn_id);
    $this->assertEquals($result->sum->__toString(), "0.1");

    //повтор возвращает 8
    $result = $this->object->pay("99999-И", 0.1, (new \DateTime)->format('YmdHis'), $txn_id)->get();
    $this->assertEquals($result->result->__toString(), "8");
  }
}
