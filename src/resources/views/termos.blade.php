<x-pagina-legal
    titulo="Termos de Uso"
    descricao="Regras de uso do CTS Convênios: o que o serviço faz, responsabilidades da prefeitura e da Castilho Soluções Digitais, disponibilidade e alertas."
    caminho="/termos"
    ultima-atualizacao="25 de setembro de 2026"
>
    <p>
        Estes termos valem para o uso do CTS Convênios, serviço da <strong>{{ config('empresa.razao_social') }}</strong>
        (CNPJ <x-dado-empresa campo="cnpj" />), pelas prefeituras contratantes e seus usuários. Eles complementam o contrato assinado com a prefeitura;
        se houver diferença, vale o contrato.
    </p>

    <h2>1. O que é o serviço</h2>
    <p>O CTS Convênios é um sistema online para a prefeitura acompanhar convênios, emendas e contratos vinculados, controlar prazos e receber alertas por
        e-mail e no sistema 90, 60, 30 e 15 dias antes de cada vencimento, além de painéis e relatórios.</p>

    <h2>2. Acesso e contas</h2>
    <ul>
        <li>O acesso é criado pela equipe da plataforma ou pelo administrador da prefeitura. Cada conta é <strong>pessoal e intransferível</strong>.</li>
        <li>Quem usa a conta é responsável por manter a senha em sigilo e por avisar imediatamente se suspeitar de uso indevido.</li>
        <li>A prefeitura deve desativar as contas de quem deixar a equipe. Podemos suspender contas que ameacem a segurança do sistema.</li>
        <li>Conforme o papel de cada pessoa — super administrador, administrador da prefeitura, gestor de convênios ou fiscal de controle interno — o sistema permite consultar,
            cadastrar e editar. A exclusão definitiva de registros é feita só pela equipe da plataforma, mediante pedido formal, e fica registrada na trilha de auditoria.
            A autenticação em duas etapas (2FA) é obrigatória para administradores.</li>
    </ul>

    <h2>3. Responsabilidades da prefeitura</h2>
    <ul>
        <li>Cadastrar dados corretos e mantê-los atualizados. Os alertas e indicadores são calculados sobre o que está cadastrado.</li>
        <li>Usar o sistema apenas para a gestão de seus convênios e contratos, respeitando a lei e não enviando conteúdo ilícito ou malicioso.</li>
        <li>Ter base legal para os dados pessoais que cadastra e atender aos pedidos dos titulares, como controladora dos dados (veja a
            <a href="/privacidade">Política de Privacidade</a>).</li>
        <li>Não tentar burlar o isolamento entre prefeituras, testar a segurança sem autorização, copiar o sistema ou revendê-lo.</li>
    </ul>

    <h2>4. Alertas e indicadores: o que esperar</h2>
    <p>Os alertas de prazo e o indicador de regularidade (como “Município Regular” ou “Risco de Inadimplência (CADIN)”) são
        <strong>ferramentas de apoio à gestão</strong>. Eles se baseiam nos prazos cadastrados pela própria prefeitura e <strong>não substituem</strong>
        a consulta oficial ao CADIN, ao CAUC, aos sistemas do governo federal ou aos instrumentos dos convênios, nem constituem certidão ou parecer.</p>
    <p>A entrega de e-mail depende de serviços de terceiros e das regras da caixa postal do destinatário (filtros de spam, por exemplo). Por isso os alertas também aparecem no
        sistema. Recomendamos que a prefeitura mantenha um responsável que acompanhe o painel.</p>

    <h2>5. Disponibilidade e suporte</h2>
    <p>Trabalhamos para manter uma meta de disponibilidade de <strong>99,5% ao ano</strong>, com cópias de segurança diárias automatizadas. Manutenções programadas que exijam
        interrupção são avisadas com pelo menos 24 horas de antecedência. O suporte técnico para dúvidas, correções e parametrizações é prestado de segunda a sexta-feira, em horário
        comercial, pelos canais definidos no contrato administrativo assinado com a prefeitura.</p>

    <h2>6. Propriedade e dados</h2>
    <ul>
        <li>O sistema, sua marca e seu código pertencem à {{ config('empresa.razao_social') }}. A contratação dá à prefeitura um direito de uso, não a propriedade.</li>
        <li>Os dados e documentos cadastrados <strong>continuam sendo da prefeitura</strong>. Usamos esses dados só para prestar o serviço.</li>
        <li>A prefeitura pode extrair seus dados a qualquer momento pelos relatórios do sistema (CSV/PDF). Ao fim regular do contrato, os dados transacionais ativos são eliminados
            da nossa base em até <strong>30 dias</strong>, ressalvada a trilha de auditoria necessária para comprovar a conformidade da prestação do serviço.</li>
    </ul>

    <h2>7. Limites de responsabilidade</h2>
    <p>Respondemos pelo serviço nos termos do contrato e da lei. Não respondemos por decisões tomadas com base em dados cadastrados de forma incorreta, omissa ou incompleta pela
        prefeitura, nem por prejuízos indiretos — perda de repasses, cancelamento de emendas, rejeição de prestação de contas pelo TCE ou bloqueio no CADIN — decorrentes de a
        prefeitura não ter agido a tempo, mesmo após receber os alertas automáticos. Esse limite não afasta responsabilidades que a lei não permite limitar. Nos termos permitidos pela
        legislação, o valor máximo de eventual indenização fica limitado ao total das mensalidades pagas pela prefeitura nos últimos <strong>6 meses</strong> de contrato.</p>

    <h2>8. Cancelamento</h2>
    <p>A prefeitura pode encerrar o contrato conforme nele previsto. Podemos suspender ou encerrar o acesso em caso de descumprimento grave destes termos, com aviso prévio sempre que possível.</p>

    <h2>9. Mudanças nestes termos</h2>
    <p>Podemos atualizar estes termos. Mudanças relevantes serão comunicadas às prefeituras com antecedência razoável, e a data no topo desta página sempre indica a versão vigente.</p>

    <h2>10. Lei aplicável e foro</h2>
    <p>Estes termos seguem a lei brasileira. Fica eleito o foro de <x-dado-empresa campo="foro" />, ressalvadas as regras de competência que a lei impõe.</p>
    <p>Dúvidas: <x-dado-empresa campo="email_contato" />.</p>
</x-pagina-legal>
