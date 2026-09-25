<x-pagina-legal
    titulo="Política de Privacidade"
    descricao="Como o CTS Convênios trata dados pessoais: o que coletamos, para quê, com quem compartilhamos e como exercer seus direitos (LGPD)."
    caminho="/privacidade"
>
    <p>
        Esta política explica como a <strong>{{ config('empresa.razao_social') }}</strong> (CNPJ <x-dado-empresa campo="cnpj" />),
        responsável pelo CTS Convênios, trata dados pessoais, em linguagem direta e conforme a Lei Geral de Proteção de Dados
        (Lei nº 13.709/2018, a LGPD).
    </p>

    <h2>1. Nosso papel em cada situação</h2>
    <ul>
        <li><strong>Site e pedidos de demonstração:</strong> somos <em>controladores</em>. Decidimos por que e como esses dados são usados.</li>
        <li><strong>Sistema usado pela prefeitura:</strong> a prefeitura contratante é a <em>controladora</em> dos dados que cadastra
            e nós somos <em>operadores</em>: tratamos os dados só para prestar o serviço contratado e conforme as instruções dela.</li>
    </ul>

    <h2>2. Que dados tratamos</h2>
    <p><strong>Quem preenche o formulário da página inicial:</strong> nome, cargo (opcional), município, e-mail, telefone (opcional),
        mensagem (opcional), endereço IP e o momento em que você aceitou o uso dos dados.</p>
    <p><strong>Quem usa o sistema (servidores e gestores da prefeitura):</strong> nome, e-mail, papel de acesso, senha (guardada de forma
        irreversível, nunca em texto aberto) e o registro das ações feitas no sistema.</p>
    <p><strong>O que a prefeitura cadastra:</strong> convênios, prazos, valores, contratos vinculados (número, empresa contratada e valor) e
        documentos enviados em arquivo. São, em geral, informações da administração pública, mas os documentos podem conter dados pessoais
        (por exemplo, nomes de servidores ou responsáveis). Não pedimos nem esperamos dados sensíveis.</p>
    <p><strong>Registros técnicos:</strong> a trilha de auditoria guarda quem alterou o quê e quando, com endereço IP, navegador e endereço
        acessado. Isso protege a prefeitura e permite investigar erros ou acessos indevidos.</p>

    <h2>3. Para que usamos e em que base legal</h2>
    <ul>
        <li><strong>Retornar seu pedido de contato ou demonstração:</strong> consentimento (art. 7º, I) e procedimentos preliminares a um contrato (art. 7º, V).</li>
        <li><strong>Prestar o serviço às prefeituras</strong> (cadastro, alertas de prazo por e-mail e no sistema, relatórios): execução do contrato (art. 7º, V)
            e, para a prefeitura, as suas atribuições legais.</li>
        <li><strong>Segurança e auditoria</strong> (limite de tentativas de login, trilha de alterações, prevenção de fraude): legítimo interesse (art. 7º, IX)
            e cumprimento de obrigação legal (art. 7º, II).</li>
    </ul>
    <p>Não vendemos dados pessoais e não usamos os dados do sistema para publicidade nem para criar perfis de comportamento.</p>

    <h2>4. Com quem compartilhamos</h2>
    <ul>
        <li><strong>Hospedagem:</strong> <x-dado-empresa campo="hospedagem" />, onde o sistema e o banco de dados ficam.</li>
        <li><strong>Envio de e-mails:</strong> o Resend (Resend, Inc.) entrega os alertas e os avisos do sistema. Ele recebe o endereço de e-mail e o texto da mensagem.</li>
        <li><strong>Autoridades:</strong> apenas quando a lei ou uma ordem judicial exigir.</li>
    </ul>
    <p>Alguns desses prestadores podem processar dados fora do Brasil. Quando isso ocorrer, exigimos garantias compatíveis com a LGPD (art. 33).</p>

    <h2>5. Cookies e armazenamento no navegador</h2>
    <p>A página inicial <strong>não usa cookies</strong>, ferramentas de análise nem rastreadores de terceiros. O sistema guarda no seu navegador o
        código de acesso (necessário para manter você conectado por até 12 horas) e preferências de tela, como o menu recolhido. Sem isso o sistema não funciona,
        e nada disso é usado para rastrear você.</p>

    <h2>6. Por quanto tempo guardamos</h2>
    <ul>
        <li><strong>Pedidos de contato:</strong> pelo tempo necessário para retornar o contato e, se não houver contratação, por até
            {{ config('contato.retencao_meses') }} {{ config('contato.retencao_meses') === 1 ? 'mês' : 'meses' }}, quando são apagados automaticamente.</li>
        <li><strong>Dados do sistema:</strong> enquanto o contrato com a prefeitura estiver ativo. Ao final, devolvemos ou eliminamos os dados conforme o contrato
            (<x-pendente>prazo a definir no contrato</x-pendente>), ressalvadas obrigações legais.</li>
        <li><strong>Cópias de segurança:</strong> mantidas por 14 dias em rodízio; dados eliminados do sistema desaparecem dos backups nesse prazo.</li>
        <li><strong>Trilha de auditoria:</strong> mantida enquanto durar o contrato, pois é o histórico que protege a prefeitura.</li>
    </ul>

    <h2>7. Como protegemos</h2>
    <ul>
        <li>Cada prefeitura só enxerga os próprios dados; o isolamento é aplicado em todas as consultas.</li>
        <li>Conexão criptografada (HTTPS), senhas guardadas de forma irreversível, limite de tentativas de login e sessão que expira em 12 horas.</li>
        <li>Documentos só são baixados por quem tem acesso ao convênio, nunca por link aberto.</li>
        <li>Trilha de auditoria de criações, alterações e exclusões, e cópias de segurança periódicas.</li>
    </ul>
    <p>Nenhum sistema é totalmente imune a incidentes. Se ocorrer um que possa causar risco relevante, avisaremos a prefeitura afetada e a ANPD nos prazos da lei.</p>

    <h2>8. Seus direitos</h2>
    <p>Você pode pedir: confirmação de que tratamos seus dados, acesso, correção, anonimização ou eliminação de dados desnecessários, portabilidade,
        informação sobre com quem compartilhamos e a revogação do consentimento (LGPD, art. 18).</p>
    <p>Para dados do formulário do site, escreva para o e-mail do encarregado abaixo. Para dados cadastrados no sistema, o pedido deve ser feito à
        <strong>prefeitura</strong> (a controladora); nós ajudamos a prefeitura a atendê-lo.</p>

    <h2>9. Encarregado e contato</h2>
    <p>Encarregado pelo tratamento de dados: <x-dado-empresa campo="encarregado_nome" />,
        e-mail <x-dado-empresa campo="encarregado_email" />.</p>
    <p>{{ config('empresa.razao_social') }} — <x-dado-empresa campo="endereco" />. Contato geral: <x-dado-empresa campo="email_contato" />.</p>
    <p>Se você entender que seus direitos não foram atendidos, pode recorrer à Autoridade Nacional de Proteção de Dados (<a href="https://www.gov.br/anpd" rel="noopener">gov.br/anpd</a>).</p>

    <h2>10. Mudanças nesta política</h2>
    <p>Quando esta política mudar de forma relevante, atualizamos a data acima e avisamos as prefeituras contratantes. Veja também os <a href="/termos">Termos de uso</a>.</p>
</x-pagina-legal>
