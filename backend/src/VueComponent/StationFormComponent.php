<?php

declare(strict_types=1);

namespace App\VueComponent;

use App\Entity\Api\Admin\Vue\StationsFormProps;
use App\Entity\Repository\CustomerRepository;
use App\Entity\Repository\ResellerRepository;
use App\Http\ServerRequest;
use App\Radio\Adapters;
use App\Radio\Enums\FrontendAdapters;
use App\Radio\StereoTool;
use App\Service\TenancyScope;
use App\Utilities\Time;
use Symfony\Component\Intl\Countries;

final readonly class StationFormComponent implements VueComponentInterface
{
    public function __construct(
        private Adapters $adapters,
        private ResellerRepository $resellerRepo,
        private CustomerRepository $customerRepo,
        private TenancyScope $tenancyScope,
    ) {
    }

    public function getProps(ServerRequest $request): StationsFormProps
    {
        $installedFrontends = $this->adapters->listFrontendAdapters(true);

        $resellerFilter = null;
        if (
            !$this->tenancyScope->isHostAdmin($request)
            && $this->tenancyScope->isResellerAdmin($request)
        ) {
            $resellerFilter = $request->getUser()->reseller;
        }

        $resellers = $this->tenancyScope->isHostAdmin($request)
            ? $this->resellerRepo->fetchSelect(true)
            : (
                null !== $resellerFilter
                    ? ['' => __('Select...'), $resellerFilter->id => $resellerFilter->name]
                    : ['' => __('Select...')]
            );

        return new StationsFormProps(
            timezones: Time::getTimezones(),
            countries: Countries::getNames(),
            isRsasInstalled: isset($installedFrontends[FrontendAdapters::Rsas->value]),
            isShoutcastInstalled: isset($installedFrontends[FrontendAdapters::Shoutcast->value]),
            isStereoToolInstalled: StereoTool::isInstalled(),
            resellers: $resellers,
            customers: $this->customerRepo->fetchSelectForReseller($resellerFilter, true),
        );
    }
}
