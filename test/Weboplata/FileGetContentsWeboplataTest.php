<?php
/**
 * Created by PhpStorm.
 * User: alexey2baranov
 * Date: 6/30/16
 * Time: 6:56 PM
 */

namespace Weboplata;


class GydraWeboplataTest extends \PHPUnit_Framework_TestCase
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
//    $this->object = new \Zend_Rest_Client("https://localhost/weboplata/public/1.0.file_get_contents.php");
    $this->object = new \Zend_Rest_Client("https://217.114.191.210/weboplata/public/1.0.php");
//    $this->object = new \Zend_Rest_Client("https://hydra.tele-plus.ru:9443/weboplata/main");
  }

  /**
   */
  public function testgetMetadata()
  {
    /** @var \Zend_Rest_Client_Result $result */
    $result = $this->object->getMetadata()
      ->AccountNumber("99999-И")
      ->PSID("dev")
      ->get();

    $this->assertTrue($result->isSuccess());
    $this->assertEquals($result->address(), "Драгой Александр Николаевич");
  }

  public function testAddIncorrectAccountNumber()
  {
    /** @var \Zend_Rest_Client_Result $result */
    $result = $this->object->add()
      ->AccountNumber("ИИИ")
      ->PaymentSumm(5)
      ->PaymentDate((new \DateTime())->format('Y-m-d\TH:i:s'))
      ->PSID("dev")
      ->ExternalPaymentID((new \DateTime)->getTimestamp())
      ->get();

    $this->assertFalse($result->isSuccess());
    $this->assertEquals("Неверный номер счета", $result->message());
  }

  public function testAddRepeateExternalPaymentId()
  {
    /** @var \Zend_Rest_Client_Result $result */
    $result = $this->object->add()
      ->AccountNumber("99999-И")
      ->PaymentSumm(1000)
      ->PaymentDate((new \DateTime())->format('Y-m-d\TH:i:s'))
      ->PSID("dev")
      ->ExternalPaymentID(1)
      ->get();

    $this->assertTrue($result->isSuccess());
  }

  public function testAdd()
  {
    /** @var \Zend_Rest_Client_Result $result */
    $result = $this->object->add()
      ->AccountNumber("99999-И")
      ->PaymentSumm(1)
      ->PaymentDate((new \DateTime())->format('Y-m-d\TH:i:s'))
      ->PSID("dev")
      ->ExternalPaymentID((new \DateTime())->format('Y-m-d\TH:i:s'))
      ->get();

    $this->assertTrue($result->isSuccess());
  }
}
