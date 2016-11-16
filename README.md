# silverstripe Send Payment Check
Adds an orderstep to e-commerce where non-paid orders are followed up.  In that email, you can ask the customer to 

a. cancel the order
b. pay for it

# Installation

Please install in `mysite/_config/ecommerce.yml` as follows:

```yml

OrderStep:
  order_steps_to_include:
    # more steps here ...
    stepX: OrderStepPaymentCheck
    # more steps here ...
    
```
X is the step number.

Note that this step replaces the `OrderStep_Paid` in most cases.  After that you will need to run a `dev/build?flush` and delete any Ordersteps that can be deleted (see: `http://mysite.co.nz/admin/shop/OrderStep`). 


# email template

In your template `OrderStepPaymentCheck_Email`, you want to add a $Order.RetrieveLink.  

# CMS Settings

You also want to make sure that customer can cancel and can pay if the order has not been paid yet (see: `http://mysite.co.nz/admin/shop/OrderStep`). 


