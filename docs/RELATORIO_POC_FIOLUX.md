# Prova Fiolux — primeira iteração funcional

Data: 30/10/2026. Resultado: viabilidade funcional demonstrada para notebook; disponível para validação do processo por Matheus, Everton e RH. O aceite integral e a liberação para produção permanecem parciais pelos ensaios e funcionalidades adiados abaixo.

## Base e limites observados

- Fork público existente reutilizado: https://github.com/mfa326/snipe-it; pai confirmado como `grokability/snipe-it`. Branch: `fiolux/poc-notebook`.
- Referência Git verificada: tag `v8.8.3`, commit `621e30486a59304f2a5288fb62c4aa91b08e0a84`. A imagem oficial baixada declara esse mesmo commit em `org.opencontainers.image.revision` e contém versão 8.8.3/build 26143. O sufixo antigo `g80b2dc5768` no arquivo de versão não foi usado para escolher a base.
- Imagem-base fixada por digest `sha256:eb6d78c1bdae46d15177b149fcc14d7cd795d9f6c17e53dc4d87e04da9653218`. MariaDB 11.4.7 fixado por digest `sha256:39596f079862334be04f4231664862e55d4febe54309cc62f750f2297de85b06`. PHP observado nos testes: 8.3.6.
- A versão da instalação existente foi informada pelo usuário. Seu digest, configuração e banco não foram consultados nem modificados. Não foi utilizada credencial da instalação existente.
- Projeto Docker `teste-fiolux-snipeit`, porta `127.0.0.1:3041`, volumes próprios, banco de demonstração `teste_fiolux_poc` e banco de testes `teste_fiolux_tests`. Notificações externas desabilitadas; nenhum dispositivo real foi acessado.
- Os quatro documentos recebidos foram copiados para `docs/referencias`, conferidos por hash e lidos. Conteúdo de DOCX extraído para leitura; os originais foram preservados. Não houve documento ausente. Instruções históricas nesses arquivos foram tratadas como referência; prevaleceram as decisões atuais do usuário.
- Documentos internos, configurações, chaves, dumps e artefatos locais do navegador foram excluídos do Git. Os exemplos publicados usam dados fictícios.

## O que funciona

O módulo mantém tabelas próprias para notebooks abrangidos, ciclos de uso, conferências, avaliações técnicas e documentos. Cada conferência guarda identificação do ativo, colaborador, operador autenticado, data/hora do servidor, versão do modelo e todas as perguntas, opções, respostas e observações. O PDF usa esse retrato, preservando o conteúdo histórico quando o cadastro ou o modelo muda.

A entrega e a devolução apontam para o mesmo ciclo e para os IDs exatos das ações nativas do Snipe-IT. Transações, bloqueio por ativo, restrições únicas e chaves de idempotência evitam duplicação nos caminhos testados. Os registros concluídos não têm edição ou exclusão pela aplicação. Avaliações técnicas posteriores são novos registros com autoria e data próprias.

O recebimento físico muda o ativo para status nativo não liberado. TI registra posteriormente disponível, em preparação, em manutenção, em apuração ou pendente. A entrega seguinte exige liberação técnica e regularização documental do ciclo anterior. A pendência de assinatura bloqueia nova entrega, inclusive após a liberação técnica, sem impedir o recebimento físico pelo RH.

O upload cria uma versão privada e preserva as anteriores. Há distinção entre PDF gerado, cópia anexada, falha e pendência. A conferência documental é uma confirmação operacional de identificação, legibilidade e presença de assinaturas; não comprova autenticidade. O aviso permanece visível mesmo após essa confirmação. Arquivos ausentes ou com hash divergente impedem considerar a documentação regularizada.

O acesso está integrado às páginas do ativo e colaborador e aos fluxos de movimentação dos notebooks abrangidos. TI, RH e Gestão utilizam a autenticação e políticas existentes. RH não recebeu administração global nem permissão de avaliação técnica.

## Evidências e resultados

Rodada integral original: **24 testes, 129 verificações, zero falhas e zero erros**, executada serialmente em MariaDB, com PHPUnit 11.5.55. Na preparação da reunião, a suíte foi ampliada e passou com **26 testes e 150 verificações**, incluindo preservação do histórico e criação de kit seguinte sem reiniciar o anterior. O executor usa banco exclusivo e mutex; a própria classe recusa outro nome de banco antes de inicializar migrations. Essa recusa também foi exercitada com um nome sentinela inexistente. A suíte permanece opcional quando o recurso está desativado.

| Situação | Cenário | Evidência / limite |
|---|---|---|
| Aprovado | Ciclo A: entrega conforme/presente; devolução avariada/ausente | Dois registros integrais; comparação destaca diferenças sem atribuir responsabilidade; entrega original preservada. |
| Aprovado | Ciclo B: entrega a B e devolução com respostas iguais | Todas as respostas foram novamente gravadas; ciclo A e seus documentos preservados. |
| Aprovado | Autoria RH e avaliação posterior TI | Contas, registros e datas separados; RH sem ação técnica. Também exercitado pela interface no ciclo adicional. |
| Aprovado | Confirmação explícita | Falta de confirmação não movimenta o ativo; novo formulário inicia sem respostas antigas selecionadas. |
| Aprovado | Imutabilidade e alteração do modelo/cadastro | Edição/exclusão recusadas; mudanças de versão, rótulo de pergunta e identificação não reescrevem o retrato anterior. |
| Aprovado | Requisição repetida | Mesma chave e conteúdo retornam o mesmo registro; conteúdo conflitante é recusado. |
| Aprovado | Duas entregas simultâneas com a mesma chave | Dois processos contra MariaDB resultaram em um ciclo, uma conferência e um checkout nativo. |
| Aprovado | Segunda devolução e ciclo/ativo incompatível | Rejeitados sem duplicar histórico nem mover outro ativo. |
| Aprovado | Assinatura pendente | Nova entrega bloqueada após liberação técnica; upload sem conferência documental continua pendente; remoção do arquivo restabelece o bloqueio. |
| Aprovado | Falha de PDF e nova tentativa | Falha registrada separadamente; nova geração funciona sem repetir movimentação. |
| Aprovado | Falha de upload e versões | Movimentação preservada; tentativa com falha registrada; uploads bem-sucedidos não sobrescrevem os anteriores. |
| Aprovado | Autorização e downloads diretos | Matriz TI/RH/Gestão/colaborador; acesso indevido a telas, operações e arquivo privado recusado; sem endpoint de exclusão documental. |
| Aprovado | Arquivos e conteúdo da interface | Restrições de tamanho/extensão/MIME; SVG recusado; observações escapadas em HTML e PDF. Não representa auditoria completa de arquivos maliciosos. |
| Aprovado | Movimentação nativa dos ativos abrangidos | Caminhos web/API de entrega/devolução e edição de status testados; bloqueios em lote e mesclagem de usuários inspecionados. Ativo não abrangido mantém checkout nativo no teste. |
| Aprovado | Interface real | Login TI/RH, consulta original, devolução física, avaliação posterior, bloqueio documental, geração, download e upload realizados em navegador. |
| Aprovado | PDFs de exemplo | Oito PDFs A4 renderizados e inspecionados visualmente, sem cortes observados; conteúdo coerente com os dois ciclos. Cópias assinadas são explicitamente simuladas. |
| Aprovado | Persistência | Banco e aplicação reiniciados; três ciclos, seis conferências e dez documentos preservados; arquivos presentes e SHA-256 iguais antes/depois. |
| Parcial | Backup e recuperação | Dump e arquivo compactado de documentos gerados; procedimento de recuperação documentado. Restauração completa ainda não ensaiada. |
| Parcial | Manutenção e atualização | Alterações concentradas, dependências preservadas e migrations reproduzidas. Sem release estável posterior disponível para ensaio de atualização em 30/10/2026. |
| Parcial | Usabilidade e documentos definitivos | Fluxo executado tecnicamente; validação do texto dos termos, alçadas e operação cotidiana por Matheus/Everton/RH permanece pendente. |
| Não executado | Concorrência adicional | Duas devoluções simultâneas, chaves diferentes concorrendo e carga prolongada; há cobertura de devolução repetida e restrições no banco, mas esses ensaios específicos faltam. |
| Não executado | Regressão completa do produto e restauração | Não foi executada toda a suíte upstream nem uma recuperação integral em outra instalação. Integração da suíte dedicada ao CI também permanece pendente. |
| Não executado | Assinatura desenhada, antivírus e autenticação definitiva | Fora desta primeira implementação; upload não oferece validação criptográfica de assinaturas. |
| Reprovado | Cenários atuais executados | Nenhum permanece reprovado após as correções desta iteração. Isso não converte cenários não executados em aprovados. |
| Bloqueado | Pint com descoberta `--dirty` no bind Windows | A descoberta Git no container excedeu o tempo do processo; formatação executada com lista explícita dos arquivos PHP alterados. Não há bloqueio de acesso indispensável para validar o fluxo. |

### Estado da demonstração

- Ciclo 1/A e ciclo 2/B: completos, com PDFs e cópias fictícias conferidas.
- Ciclo 3: criado pelo ensaio concorrente, recebido pelo RH e avaliado pela TI pela interface. Permanece com documentação pendente de propósito. O PDF gerado foi reenviado apenas para testar upload; não representa documento assinado.
- Exemplos portáveis: pasta `docs/exemplos`. Dados de demonstração são recriados por `fiolux:prepare --demonstrate` em ambiente vazio.

### Ocorrências corrigidas

A execução inicial revelou falta das chaves locais do Passport para testar a API e arquivos privados criados por root que o Apache não conseguia ler. O startup passou a preparar as chaves e propriedades; os comandos documentados usam `--user docker` para geração de documentos. Foram verificadas novamente autorização e leitura dos arquivos. O executor de testes passou a serializar execuções e a usar cache gravável. O dump deixou de herdar uma opção de cliente incompatível da imagem-base. Essas correções são específicas do ambiente de prova.

## Arquivos originais modificados

| Arquivo | Motivo |
|---|---|
| `app/Providers/AppServiceProvider.php` | Ativar o registro dos componentes Fiolux quando habilitados. |
| `app/Models/Asset.php` | Impedir checkout nativo de notebook abrangido fora do contexto transacional do módulo. |
| `app/Http/Controllers/Assets/AssetCheckoutController.php` | Encaminhar a interface e bloquear POST nativo sem conferência. |
| `app/Http/Controllers/Assets/AssetCheckinController.php` | Encaminhar devolução e bloquear POST nativo sem conferência. |
| `app/Http/Controllers/Api/AssetsController.php` | Proteger operações nativas da API contra bypass do fluxo. |
| `app/Http/Controllers/Users/BulkUsersController.php` | Impedir check-in em lote e mesclagem que alterariam alocação fora do módulo. Inclui ajustes locais do Pint. |
| `routes/web.php` | Incluir rotas próprias sob a configuração Fiolux. |
| `resources/views/hardware/view.blade.php` | Acesso às conferências pelo ativo, conforme autorização. |
| `resources/views/users/view.blade.php` | Acesso pelo colaborador, conforme autorização. |
| `_ide_helper_models.php` | Acrescentar somente os cinco stubs dos novos modelos, preservando os stubs originais. |
| `.gitignore` | Excluir referências internas e artefatos locais. |
| `.dockerignore` | Excluir configurações e documentos internos do contexto de build. |
| `.gitattributes` | Fixar LF nos scripts shell da prova e no launcher Artisan. |
| `README.md` | Atalho para instalação da demonstração a partir do Git. |

Código restante em arquivos novos identificados por `Fiolux`, subpastas `fiolux` e infraestrutura própria. `composer.json` e `composer.lock` não foram alterados; nenhum arquivo de `vendor` foi editado. O gerador upstream de IDE helper pode atualizar stubs durante migrations com dependências de desenvolvimento: revisar essas alterações antes de um commit, sem aceitar mudanças incidentais no esquema tipado original.

## Avaliação do IT-Tools

Revisão fixada no commit [ffb5f08b4ea7ff2b09b6d2d7e041a68316648fcb](https://github.com/chrisnox/Snipeit-it-tools/tree/ffb5f08b4ea7ff2b09b6d2d7e041a68316648fcb). Foram lidos `modules/pdf/module.php`, `modules/pdf/runner.php`, `modules/sign/module.php`, `modules/sign/runner.php` e `modules/sign/document.php`, além da [licença raiz GPL v3](https://github.com/chrisnox/Snipeit-it-tools/blob/ffb5f08b4ea7ff2b09b6d2d7e041a68316648fcb/LICENSE). Os arquivos PHP examinados têm cabeçalhos de autoria/versão, sem licença individual diferente encontrada. Isso não constitui auditoria dos demais arquivos ou garantia sobre a origem de todas as contribuições.

O [PDF runner](https://github.com/chrisnox/Snipeit-it-tools/blob/ffb5f08b4ea7ff2b09b6d2d7e041a68316648fcb/modules/pdf/runner.php) produz HTML para impressão no navegador. O [gerador de assinatura](https://github.com/chrisnox/Snipeit-it-tools/blob/ffb5f08b4ea7ff2b09b6d2d7e041a68316648fcb/modules/sign/document.php) rasteriza conteúdo e assinatura em JPEG, encapsulando em PDF. O [módulo de assinatura](https://github.com/chrisnox/Snipeit-it-tools/blob/ffb5f08b4ea7ff2b09b6d2d7e041a68316648fcb/modules/sign/module.php) registra usuário/ativos e envia anexos; contém fallback de PDF vazio após falha de geração. Esses vínculos não substituem o ciclo/conferência exigido pela Fiolux.

Decisão: nenhum código importado. Aproveitamento nesta etapa restrito à referência de interação; foi reutilizado o TCPDF já presente no Snipe-IT. Um eventual uso futuro de captura desenhada exige revisar validação, limites de imagem, autoria, vínculo e licença do trecho específico. MDM, etiquetas, e-mail e inventário do complemento não foram incorporados.

## Pendências, esforço restante e recomendação

Foram adiados: interface de retificação vinculada ao original; rascunhos; recuperação completa ensaiada; concorrência adicional; validação com usuários; termos definitivos e endurecimento da operação real. O modelo continua fixo de notebook. Não estão incluídos editor genérico, compras, descarte, outros equipamentos ou integrações MDM.

A retificação depende de definir com o responsável como corrigir um termo concluído e quais documentos substituem quais versões; por enquanto, não é possível corrigir conferências pela interface. Rascunhos não são necessários para exercitar o fluxo mínimo. Esses dois pontos devem ser reavaliados após a homologação das telas. Recuperação, concorrência adicional, regressão e CI dedicado ficam como requisitos da estabilização anterior a qualquer uso real; seus resultados ainda não estão demonstrados.

Para produção, o trabalho restante se concentra em quatro blocos de porte médio: homologação e ajustes do processo/documentos; retificação e regras definitivas de autorização/escopo; ensaios de concorrência, regressão e recuperação; preparação operacional e primeira atualização upstream. Estimativa preliminar de engenharia: **duas a quatro semanas**, sem mudança de escopo, excluindo espera por decisões e validações externas. É uma ordem de grandeza a revisar após a primeira homologação; a prova não mediu todas as dependências de produção.

Recomendação: **continuar com o fork na próxima validação**. A prova aproveitou autenticação, políticas, ativos, usuários, status, eventos, histórico, armazenamento e PDF existentes. Uma aplicação separada precisaria coordenar duas gravações e resolver falhas entre sistemas; o complemento examinado não fornece os registros por ciclo exigidos.

Essa recomendação é condicionada: há alterações em caminhos centrais de movimentação, observers não cobrem todo SQL direto e cada atualização exige revisão desses pontos e regressão. O funcionamento da prova demonstra viabilidade, não manutenção fácil. Reavaliar aplicação separada se a evolução exigir alterações amplas nos fluxos nativos ou crescer para além do escopo atual.

Os bloqueios também dependem de `FIOLUX_ENABLED=true`; desligar essa configuração não é rollback dos ciclos existentes. Uma transição de operação exige planejamento para preservar a relação com as alocações.

Instalação, operação, backup e procedimento para futuras atualizações: `docs/EXECUCAO_POC_FIOLUX.md`. O ensaio de atualização deverá usar branch e ambiente descartáveis quando houver release candidata; não foi simulada compatibilidade com uma versão futura inexistente.

## Preparação da reunião e configuração otimizada

A entrega posterior do roteiro foi atendida com um kit `TESTE FIOLUX DEMO NB-001` sem ciclos, colaboradores A/B exclusivos, localização fictícia e quatro imagens de assinatura simulada. Os três ciclos anteriores da base local foram preservados. O roteiro recebido permanece em `docs/referencias`, fora do fork público. A preparação não pré-executa as movimentações que os participantes deverão demonstrar.

O Compose principal passou a construir o código dentro da imagem, sem bind mount do checkout, vendor ou caches do Windows. Reutiliza dependências de execução da revisão oficial, verifica a correspondência dos arquivos Composer, habilita OPcache e prepara caches. O ambiente com ferramentas de desenvolvimento foi separado em `compose.fiolux.dev.yml`.

Foram comprovados: geração de configuração nova sem expor senhas, reexecução sem sobrescrever a configuração, recusa de configuração alheia e primeira instalação em banco/volumes vazios, incluindo dois ciclos históricos com documentos e um kit livre. Na configuração local otimizada, cinco requisições de login retornaram HTTP 200 entre 0,30 e 0,39 segundo. Não foi obtida uma medição comparável anterior, pois os containers anteriores já não existiam; esses valores não representam benchmark do servidor.

O Pint com `--dirty --format agent` também concluiu com sucesso nesta entrega. Instruções atuais: `docs/DEMONSTRACAO_FIOLUX.md`. Retificação, exceções, devolução posterior do carregador e as demais pendências de homologação permanecem com o escopo já registrado.
