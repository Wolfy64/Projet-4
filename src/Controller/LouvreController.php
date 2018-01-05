<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Reservation;
use App\Entity\Ticket;
use App\Form\ReservationType;
use App\Repository\TicketRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Services\Compute;

class LouvreController extends Controller
{
    public function index(Request $request, Session $session, Compute $compute, TicketRepository $numberTickets)
    {
        $reservation = new Reservation();
        $ticket      = new Ticket();
        $reservation->addTicket($ticket);

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $compute->setReservation($reservation);
            $compute->price();

            if (!$compute->isCostValid()){
                $this->addFlash('notice', $reservation->getCost() . '€ is an insufficient amount to order online.');
                return $this->redirectToRoute('index');
            }

            $hasTickets = $compute->hasTickets($numberTickets);

            if ($hasTickets === 'yes'){
                $session->set('reservation', $reservation);
                return $this->render('louvre/payment.html.twig', [
                    'amount'      => $reservation->getCost() * 100,
                    'email'       => $reservation->getEmail(),
                    'countTicket' => count($reservation->getTickets()),
                    'bookingDate' => $reservation->getBookingDate()->format('l d F Y'),
                    'tickets'     => $reservation->getTickets()
                ]);
            }elseif ($hasTickets === 'none') {
                $this->addFlash('notice', 'Sorry, tickets for The Louvre Museum are sold out !');
            }else {
                $this->addFlash('notice', 'Sorry, only ' . $hasTickets . ' tickets left !');
            }
        }

        return $this->render('louvre/index.html.twig',[
                'form' => $form->createView()
            ]);
    }

    public function paymentProcess(Request $request, Session $session, \Swift_Mailer $mailer)
    {
        $reservation = $session->get('reservation');

        $stripe = [
            "secret_key"      => "sk_test_vR13pPT8iogxBJKWC1FOuDDj",
            "publishable_key" => "pk_test_zwG6fcavFG9NgdGA3aOaY2oZ"
        ];

        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        $customer = \Stripe\Customer::create([
            'email'  => $request->get('stripeEmail'),
            'source' => $request->get('stripeToken')
        ]);

        try {
            $charge = \Stripe\Charge::create([
                'customer'    => $customer->id,
                'amount'      => $reservation->getCost() * 100,
                'currency'    => 'eur',
                'description' => 'Louvre'
            ]);
            $payment = true;
        } catch (\Stripe\Error\Card $e) {
            // Since it's a decline, \Stripe\Error\Card will be caught
            $body = $e->getJsonBody();
            $error = $body['error']['message'];
            $payment = false;
        } catch (\Stripe\Error\RateLimit $e) {
            // Too many requests made to the API too quickly
            $body = $e->getJsonBody();
            $error = $body['error']['message'];
            $payment = false;
        } catch (\Stripe\Error\InvalidRequest $e) {
            // Invalid parameters were supplied to Stripe's API
            $body = $e->getJsonBody();
            $error = $body['error']['message'];
            $payment = false;
        } 

        // Insert data in database
        if ( $payment === true ){
            $em = $this->getDoctrine()->getManager();
            $em->persist($reservation);
            $em->flush();
            return $this->redirectToRoute('mails');
        }

        $this->addFlash('notice', $error);
        return $this->render('louvre/payment.html.twig', [
            'amount' => $reservation->getCost() * 100,
            'email' => $reservation->getEmail(),
            'countTicket' => count($reservation->getTickets()),
            'bookingDate' => $reservation->getBookingDate()->format('l d F Y'),
            'tickets' => $reservation->getTickets()
        ]);
    }

    public function mails(Session $session, \Swift_Mailer $mailer)
    {
        $reservation = $session->get('reservation');

        $message = (new \Swift_Message('Order Receipt'))
            ->setFrom('ledavid64@gmail.com')
            ->setTo($reservation->getEmail())
            ->setBody($this->renderView('emails/order.html.twig',[
                    'bookingDate' => $reservation->getBookingDate()->format('l d F Y'),
                    'tickets'     => $reservation->getTickets(),
                    'amount'      => $reservation->getCost(),
                    'code'        => \substr(\sha1($reservation->getEmail()), 0, 8)
                ]),
                'text/html'
        );

        $mailer->send($message);

        $this->addFlash('notice','Your order receipt has been sent to your email !');

        return $this->redirectToRoute('index');
    }
}