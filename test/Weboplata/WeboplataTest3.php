<?php
/**
 * Created by PhpStorm.
 * User: alexey2baranov
 * Date: 6/30/16
 * Time: 6:56 PM
 */

namespace Weboplata;


class Weboplata3Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Weboplata3
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new \Zend_Rest_Client("http://localhost/weboplata/public/3.0.php");
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
    public function testgetAccountNumberByPhoneNumber()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= $this->object->getAccountNumberByPhoneNumber("555555")
            ->get();

        $this->assertTrue($result->isSuccess());
    }

    /**
     */
    public function testgetMetadata()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= $this->object->getMetadata("789-И", "unit-test", 8)
            ->get();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($result->address(), "д. Сайгатина, ул. Совхозная, д.11, кв.22");
    }

    public function testAddIncorrectAccountNumber()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= $this->object->add("ИИИ", 5, (new \DateTime())->format('Y-m-d\TH:i:s'), "unit-test", 8, (new \DateTime)->getTimestamp())
            ->get();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Неверный номер счета", $result->message());
    }

    public function testAddIncorrectPhoneNumber()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= $this->object->add("999999", 5, (new \DateTime())->format('Y-m-d\TH:i:s'), "unit-test", 7, (new \DateTime)->getTimestamp())
            ->get();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Неверный номер телефона", $result->message());
    }

    public function testAddRepeateExternalPaymentId()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= $this->object->add("789-И", 5, (new \DateTime())->format('Y-m-d\TH:i:s'), "Терминал Офис", 8, 1427962943)
            ->get();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Платеж с таким вшеншим идентификатором уже существует", $result->message());
    }

    public function testAdd()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= $this->object->add("1001", 1, (new \DateTime)->format('Y-m-d\TH:i:s'), "Сбербанк", 8, (new \DateTime)->getTimestamp())
            ->get();

        $this->assertTrue($result->isSuccess());
    }

    public function testAddDirect()
    {
        /** @var \Zend_Rest_Client_Result $result */
        $result= (new Weboplata3)->add("1001", 1, (new \DateTime)->format('Y-m-d\TH:i:s'), "Сбербанк", 8, (new \DateTime)->getTimestamp()+1);

        $this->assertEquals(Weboplata3::OK, $result);
    }

/*    public function testIsAerotel($AccountNumber)
    {
        $weboplata= new Weboplata3;

        $this->assertEquals($weboplata->isAerotel("789-И"), true);
        $this->assertEquals($weboplata->isAerotel("А789-И"), true);
        $this->assertEquals($weboplata->isAerotel("789-И"), false);
    }*/
}
