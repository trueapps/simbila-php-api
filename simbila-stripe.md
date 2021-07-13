# Simbila.com PHP API with Stripe and JS

Implementation of recurring billing and simbila usage with Stripe implemented. 
Goal is to provider Stripe CC input and initiate new recurring billing with saving entering CC.
The process:
##### 3. Prerequisites
- valid simbila account
- jquery initialized on client side


##### 2. On your page insert this code:


    <div id='simbila-stripe-payment'></div>
    <script src="https://www.simbila.com/js/loadingoverlay.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://www.simbila.com/js/simbila-stripe.js"></script>
    <script type="text/javascript">
        var simbilaStripe = null;
        $(document).ready(function(){
            var params = {
                'element' : $('#simbila-stripe-payment'),
                'stripe_key' : '{YOUR_STRIPE_PUBLISHABLE_KEY}',
                'createBillingUrl' : '{YOUR_SERVER_API_URL}',
                'messages' : {
                    'payBtn' : 'Zaplatit',
                    'successMsg' : 'Děkujeme vám za Vaši platbu!',
                },
                'successCallback' : function(){
                    alert('hej, dopadlo to');
                },
                'billing_details' : {
                    'name' : 'Tomas Hnilica',
                    'address' : {
                        'postal_code' : '62700',
                    }
                }        
            };
            simbilaStripe = new SimbilaStripe(params);
            simbilaStripe.init();

        });

    </script>


##### 3.Your server API endpoint example

        public function actionCreateBilling() {
            $id = account()->id;
            $account = account();
            $useCC = true; /*to obtain stripe client_secret*/

            $simbila = Yii::app()->simbila->simbila;  /*get initialted simbila */
            $simbila->setAppUser($id); /*set user local app ID - user that will be billed*/

            /*enter user data for recurring billing*/
            $data = array(
                'fy_name' => $account->invoiceName,
                'fy_email' => $account->invoiceEmail,
                'fy_ico' => $account->ico,
                'fy_dic' => $account->dic,
                'fy_street' => $account->invoiceStreet,
                'fy_city' => $account->invoiceCity,
                'fy_zip' => $account->invoiceZip,
                'fy_country' => $account->invoiceCountry,
                'fy_bank_account' => $account->bank_account,
                'fy_bank_code' => $account->bank_code,
                'plan_text' => 'Lunchdrive',
                'plan_price' => '1290',
                'plan_vat' => '21',
                'plan_pcs' => '1',
                'r_lang' => 'cs',
                'r_recurring_desc' => 'lunchdrive',
                'useCC' => $useCC,
            );

            /*calls Simbila and creates recurring billing. Returning client_secret for js stripe processing*/
            $ret = $simbila->createBilling($data);
            $rst = $ret->obj();

            header('Content-Type: application/json');
            echo json_encode($rst);        
            Yii::app()->end();
        }

