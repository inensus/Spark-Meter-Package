let routes = [
  {
    path: '/spark-meters/sm-meter-model/page/:page_number',
    component: require('./plugins/spark-meter/js/components/MeterModel/MeterModelList').default,
    meta: { layout: 'default' },
  },
  {
    path: '/spark-meters/sm-customer/page/:page_number',
    component: require('./plugins/spark-meter/js/components/Customer/CustomerList').default,
    meta: { layout: 'default' },
  },
  {
    path: '/spark-meters/sm-tariff/page/:page_number',
    component: require('./plugins/spark-meter/js/components/Tariff/TariffList').default,
    meta: { layout: 'default' },
  },
  {
    path: '/spark-meters/sm-tariff/:id',
    component: require('./plugins/spark-meter/js/components/Tariff/TariffDetail').default,
    meta: { layout: 'default' },
  },
  {
    path: '/spark-meters/sm-overview',
    component: require('./plugins/spark-meter/js/components/Overview/Overview').default,
    meta: { layout: 'default' },
  },
]
