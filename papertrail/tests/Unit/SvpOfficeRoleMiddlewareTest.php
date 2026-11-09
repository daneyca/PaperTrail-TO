<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SvpOfficeRoleMiddlewareTest extends TestCase
{
    public function test_office_preparation_rules_preserve_the_bac_secretariat_pr_capability(): void
    {
        $user = new User(['user_id' => 'BACSEC-001', 'role' => User::ROLE_BAC_SECRETARIAT, 'status' => User::STATUS_ACTIVE]);
        $user->setRelation('assignedRole', null);
        $request = Request::create('/head-office/purchase-requests/create');
        $request->setUserResolver(fn () => $user);
        $route = (new Route(['GET'], 'head-office/purchase-requests/create', fn () => null))->name('head-office.pr.create');
        $request->setRouteResolver(fn () => $route);

        $response = app(EnsureUserHasRole::class)->handle($request, fn () => new Response('allowed'), 'head-office');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }
}
