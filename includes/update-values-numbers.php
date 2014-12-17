<?php
/*
    Update a number of options values from english strings / slugs to numbers
*/
switch( $options['exp-status'] )
{
    case 'Hold':
        $options['exp-status'] = '0';
        break;
    case 'Delete':
        $options['exp-status'] = '2';
        break;
    default:
        $options['exp-status'] = '1';
} // end switch
switch( $options['chg-status'] )
{
    case 'No Change':
        $options['chg-status'] = '0';
        break;
    case 'Pending':
        $options['chg-status'] = '1';
        break;
    case 'Private':
        $options['chg-status'] = '3';
        break;
    default:
        $options['chg-status'] = '2';
}
/*
$r = (1 == $v) ? 'Yes' : 'No'; // $r is set to 'Yes'
$r = (3 == $v) ? 'Yes' : 'No'; // $r is set to 'No'
*/
$options['chg-sticky'] = ( 'No Change' == $options['chg-sticky'] ) ? '0' : '1';
switch( $options['chg-cat-method'] )
{
    case 'Add selected':
        $options['chg-cat-method'] = '1';
        break;
    case 'Remove selected':
        $options['chg-cat-method'] = '2';
        break;
    case 'Match selected':
        $options['chg-cat-method'] = '3';
        break;
    default:
        $options['chg-cat-method'] = '0';
}
$options['notify-on'] = ( 'Notification off' == $options['notify-on'] ) ? '0' : '1';
$options['notify-admin'] = ( 'Do not notify admin' == $options['notify-admin'] ) ? '0' : '1';
$options['notify-author'] = ( 'Do not notify author' == $options['notify-author'] ) ? '0' : '1';
$options['notify-expire'] = ( 'Do not notify on expiration' == $options['notify-expire'] ) ? '0' : '1';
$options['show-columns'] = ( 'Do not show expiration in columns' == $options['show-columns'] ) ? '0' : '1';
$options['datepicker'] = ( 'Do not use datepicker' == $options['datepicker'] ) ? '0' : '1';
$options['remove-cs-data'] = ( 'Do not remove data' == $options['remove-cs-data'] ) ? '0' : '1';
?>