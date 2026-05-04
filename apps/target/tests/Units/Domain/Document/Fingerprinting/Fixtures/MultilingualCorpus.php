<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document\Fingerprinting\Fixtures;

/**
 * Test corpus for fingerprinting scenarios — synthetic, copyright-free,
 * roughly news-article-shaped. Three sizes per language so we can
 * separately exercise the short / medium / long behaviour of each
 * generator.
 *
 *   - short:  ~25–35 words / 50–80 CJK chars
 *   - medium: ~120–160 words / 250–350 CJK chars
 *   - long:   ~280–360 words / 700–900 CJK chars
 *
 * Texts are pre-normalised (lowercase, no punctuation) so tests can
 * focus on the algorithms under test rather than re-running the
 * normaliser on every iteration.
 */
final class MultilingualCorpus
{
    // ─── English ─────────────────────────────────────────────────────────

    public const string EN_SHORT = 'the european council met on monday to discuss new climate '
        . 'targets the leaders agreed on a binding framework that will reduce '
        . 'emissions across the bloc by twenty percent before the end of the decade';
    public const string EN_MEDIUM = 'the european council met on monday to discuss new climate '
        . 'targets the leaders agreed on a binding framework that will reduce '
        . 'emissions across the bloc by twenty percent before the end of the decade '
        . 'the agreement was reached after several rounds of negotiation between '
        . 'member states many of which had been pushing for more ambitious goals '
        . 'industry representatives welcomed the predictability brought by the '
        . 'new rules but warned that competitiveness must remain a priority '
        . 'environmental groups praised the binding nature of the framework while '
        . 'noting that the headline figure falls short of what scientists have '
        . 'recommended national governments will now translate the targets into '
        . 'domestic legislation over the coming year';
    public const string EN_LONG = self::EN_MEDIUM . ' analysts pointed to the '
        . 'inclusion of a market stability mechanism as the most innovative element '
        . 'of the deal under the new mechanism credits will be retired automatically '
        . 'when the carbon price falls below a defined threshold preventing the '
        . 'kind of supply gluts that hampered earlier schemes the council also '
        . 'agreed to phase down free allowances for heavy industries by twenty '
        . 'twenty seven though some sectors will retain protection until twenty '
        . 'thirty negotiators from eastern member states secured a transition fund '
        . 'aimed at communities that depend on coal mining the fund will be '
        . 'capitalised by auction revenues and is expected to total between fifty '
        . 'and seventy billion euros over the program lifetime an initial '
        . 'distribution will be allocated based on a deprivation index rather than '
        . 'historical emissions opposition leaders criticised the timeline as '
        . 'unrealistic and demanded a sectoral exemption for agriculture which was '
        . 'ultimately rejected the parliament will hold its first reading next month';

    // ─── French ──────────────────────────────────────────────────────────

    public const string FR_SHORT = 'le conseil européen s est réuni lundi pour discuter '
        . 'de nouveaux objectifs climatiques les dirigeants ont adopté un cadre '
        . 'contraignant qui réduira les émissions du bloc de vingt pour cent '
        . 'avant la fin de la décennie';
    public const string FR_MEDIUM = 'le conseil européen s est réuni lundi pour discuter '
        . 'de nouveaux objectifs climatiques les dirigeants ont adopté un cadre '
        . 'contraignant qui réduira les émissions du bloc de vingt pour cent '
        . 'avant la fin de la décennie l accord a été conclu après plusieurs '
        . 'cycles de négociation entre états membres dont beaucoup demandaient '
        . 'des objectifs plus ambitieux les industriels ont salué la prévisibilité '
        . 'apportée par les nouvelles règles tout en alertant sur la nécessité '
        . 'de préserver la compétitivité les associations écologistes ont '
        . 'approuvé le caractère contraignant du cadre tout en regrettant que '
        . 'le chiffre principal reste en deçà des préconisations scientifiques '
        . 'les gouvernements nationaux devront maintenant traduire ces objectifs '
        . 'dans leur droit interne au cours de la prochaine année';
    public const string FR_LONG = self::FR_MEDIUM . ' les analystes ont souligné '
        . 'que l inclusion d un mécanisme de stabilité du marché constitue '
        . 'l élément le plus novateur de l accord ce mécanisme retirera '
        . 'automatiquement des crédits lorsque le prix du carbone passera '
        . 'sous un seuil donné évitant ainsi les surplus qui avaient affaibli '
        . 'les dispositifs précédents le conseil a également décidé de '
        . 'supprimer progressivement les quotas gratuits accordés à l industrie '
        . 'lourde d ici vingt vingt sept même si certains secteurs conserveront '
        . 'une protection jusqu en vingt trente les négociateurs des états '
        . 'd europe orientale ont obtenu un fonds de transition destiné aux '
        . 'communautés dépendant du charbon ce fonds sera financé par les '
        . 'recettes des enchères et devrait atteindre entre cinquante et '
        . 'soixante dix milliards d euros sur la durée du programme la '
        . 'répartition initiale s appuiera sur un indice de précarité plutôt '
        . 'que sur les émissions historiques les oppositions ont critiqué le '
        . 'calendrier jugé irréaliste et exigé une exemption sectorielle pour '
        . 'l agriculture rejetée en dernière instance le parlement entamera '
        . 'sa première lecture le mois prochain';

    // ─── Chinese ─────────────────────────────────────────────────────────

    public const string ZH_SHORT = '欧洲理事会周一召开会议讨论新的气候目标领导人就一项'
        . '具有约束力的框架达成共识该框架将在本十年结束前将整个地区的排放量减少百分之二十';
    public const string ZH_MEDIUM = '欧洲理事会周一召开会议讨论新的气候目标领导人就一项'
        . '具有约束力的框架达成共识该框架将在本十年结束前将整个地区的排放量减少百分之二十'
        . '协议是在成员国之间多轮谈判之后达成的其中许多国家此前一直推动更宏大的目标'
        . '工业代表对新规则带来的可预测性表示欢迎但警告称必须保持竞争力'
        . '环保组织赞扬该框架的约束性质同时指出标题数字低于科学家的建议'
        . '各国政府现在将在未来一年内把这些目标转化为国内立法';
    public const string ZH_LONG = self::ZH_MEDIUM . '分析师指出市场稳定机制的纳入'
        . '是该协议中最具创新性的要素根据新机制当碳价格跌破设定阈值时'
        . '配额将被自动取消防止出现以往计划中曾经困扰市场的供应过剩'
        . '理事会还同意在二零二七年之前逐步取消重工业的免费配额'
        . '尽管某些部门将保留保护直至二零三零年东欧成员国谈判代表'
        . '为依赖煤矿的社区争取到一项过渡基金该基金将由拍卖收入资助'
        . '预计在项目生命周期内总额介于五百亿到七百亿欧元之间'
        . '初始分配将基于贫困指数而非历史排放量反对派领导人批评时间表不切实际'
        . '并要求对农业部门进行豁免最终被拒绝议会将于下月进行首次审议';

    // ─── Japanese ────────────────────────────────────────────────────────

    public const string JA_SHORT = '欧州理事会は月曜日に新たな気候目標について議論する'
        . 'ため会合を開き指導者たちは加盟国全体の排出量を本十年末までに'
        . '二十パーセント削減する拘束力のある枠組みに合意した';
    public const string JA_MEDIUM = '欧州理事会は月曜日に新たな気候目標について議論する'
        . 'ため会合を開き指導者たちは加盟国全体の排出量を本十年末までに'
        . '二十パーセント削減する拘束力のある枠組みに合意した'
        . '協定はより野心的な目標を求めてきた多くの加盟国の間で行われた'
        . '数回の交渉の後に成立した産業界の代表は新しい規則がもたらす予測可能性を歓迎したが'
        . '競争力を維持しなければならないと警告した環境団体は枠組みの拘束力ある'
        . '性質を称賛したが見出しの数字は科学者の推奨を下回っていると指摘した'
        . '各国政府は今後一年間でこれらの目標を国内法に転換することになる';
    public const string JA_LONG = self::JA_MEDIUM . 'アナリストは市場安定メカニズムの'
        . '組み込みが取引の中で最も革新的な要素であると指摘した新しい仕組みの下では'
        . '炭素価格が一定の閾値を下回った場合に枠が自動的に取り消され'
        . '以前の制度を悩ませた供給過剰を防止する理事会は重工業に対する無償割当を'
        . '二零二七年までに段階的に廃止することにも合意したが一部の分野は'
        . '二零三零年まで保護を維持する東欧加盟国の交渉担当者は石炭採掘に'
        . '依存する地域社会のための移行基金を獲得しこの基金は競売収入によって'
        . '資金を調達されプログラム期間中に五百億から七百億ユーロに達すると見込まれている'
        . '初期配分は歴史的排出量ではなく剥奪指数に基づいて配分される'
        . '野党指導者はスケジュールが非現実的だと批判し農業分野への適用除外を要求したが'
        . '最終的に却下された議会は来月最初の審議を行う';

    // ─── Korean ──────────────────────────────────────────────────────────

    public const string KO_SHORT = '유럽 이사회는 월요일에 새로운 기후 목표를 논의하기'
        . '위해 회의를 열었으며 지도자들은 이번 십 년 말까지 블록 전체의 배출량을'
        . '이십 퍼센트 감축하는 구속력 있는 체계에 합의했다';
    public const string KO_MEDIUM = '유럽 이사회는 월요일에 새로운 기후 목표를 논의하기'
        . '위해 회의를 열었으며 지도자들은 이번 십 년 말까지 블록 전체의 배출량을'
        . '이십 퍼센트 감축하는 구속력 있는 체계에 합의했다'
        . '이 협정은 더욱 야심찬 목표를 추진해 온 많은 회원국들 사이에서'
        . '여러 차례의 협상 끝에 도출되었다 산업계 대표들은 새 규칙이 가져올'
        . '예측 가능성을 환영했지만 경쟁력은 우선순위로 유지되어야 한다고 경고했다'
        . '환경 단체들은 체계의 구속력 있는 성격을 높이 평가하면서도'
        . '대표 수치가 과학자들의 권고에 미치지 못한다고 지적했다'
        . '각국 정부는 이제 향후 일 년 동안 이러한 목표를 국내 법으로 전환할 예정이다';
    public const string KO_LONG = self::KO_MEDIUM . ' 분석가들은 시장 안정 메커니즘의'
        . '포함이 합의에서 가장 혁신적인 요소라고 지적했다 새로운 메커니즘에 따라'
        . '탄소 가격이 정해진 임계값 아래로 떨어지면 크레딧이 자동으로 회수되어'
        . '이전 제도를 괴롭혔던 공급 과잉을 방지하게 된다 이사회는 또한 중공업에'
        . '대한 무상 배출권을 이천이십칠 년까지 단계적으로 폐지하기로 합의했지만'
        . '일부 부문은 이천삼십 년까지 보호가 유지된다 동유럽 회원국 협상 대표들은'
        . '석탄 광산에 의존하는 지역 사회를 위한 전환 기금을 확보했으며'
        . '이 기금은 경매 수익으로 자본화되며 프로그램 수명 동안 오백억에서'
        . '칠백억 유로 사이의 총액에 이를 것으로 예상된다 초기 분배는 역사적'
        . '배출량보다 결핍 지수에 따라 이루어진다 야당 지도자들은 일정이'
        . '비현실적이라고 비판하며 농업 분야 면제를 요구했으나 결국 거부되었다'
        . '의회는 다음 달에 첫 독회를 가질 예정이다';

    /**
     * Iterate over all `(language, length, text)` combinations as a
     * PHPUnit data provider source.
     *
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function provider(): iterable
    {
        $matrix = [
            'en-short' => ['en', 'short', self::EN_SHORT],
            'en-medium' => ['en', 'medium', self::EN_MEDIUM],
            'en-long' => ['en', 'long', self::EN_LONG],
            'fr-short' => ['fr', 'short', self::FR_SHORT],
            'fr-medium' => ['fr', 'medium', self::FR_MEDIUM],
            'fr-long' => ['fr', 'long', self::FR_LONG],
            'zh-short' => ['zh', 'short', self::ZH_SHORT],
            'zh-medium' => ['zh', 'medium', self::ZH_MEDIUM],
            'zh-long' => ['zh', 'long', self::ZH_LONG],
            'ja-short' => ['ja', 'short', self::JA_SHORT],
            'ja-medium' => ['ja', 'medium', self::JA_MEDIUM],
            'ja-long' => ['ja', 'long', self::JA_LONG],
            'ko-short' => ['ko', 'short', self::KO_SHORT],
            'ko-medium' => ['ko', 'medium', self::KO_MEDIUM],
            'ko-long' => ['ko', 'long', self::KO_LONG],
        ];

        foreach ($matrix as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * Apply a single-character typo near the middle of the text. Used
     * to build a "minor edit" pair that should still be detected as
     * near-duplicate.
     */
    public static function withTypo(string $text): string
    {
        $isCjk = preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text) > 0;
        if ($isCjk) {
            // Replace one CJK char somewhere in the middle.
            $chars = mb_str_split($text, 1, 'UTF-8');
            $mid = (int) (\count($chars) / 2);
            $chars[$mid] = '愛'; // unrelated CJK glyph chosen for diff

            return implode('', $chars);
        }

        // Latin path: change one letter in a middle word.
        $words = explode(' ', $text);
        $mid = (int) (\count($words) / 2);
        $words[$mid] = strrev($words[$mid]);

        return implode(' ', $words);
    }

    /**
     * Prepend a short intro to the text so the new shingle set
     * substantially overlaps the original — emulating "republished
     * with editorial header" cases.
     */
    public static function withIntroAdded(string $text, string $lang): string
    {
        return self::introFor($lang) . ' ' . $text;
    }

    private static function introFor(string $lang): string
    {
        return match ($lang) {
            'en' => 'breaking news this morning a major announcement just came out',
            'fr' => 'flash info ce matin une annonce majeure vient de tomber',
            'zh' => '今天上午突发新闻刚刚传来一项重大公告',
            'ja' => '今朝の速報重要な発表が今しがた届きました',
            'ko' => '오늘 아침 속보 중요한 발표가 방금 전해졌습니다',
            default => throw new \InvalidArgumentException("Unknown language: {$lang}"),
        };
    }

    /**
     * Return an unrelated text in the same language and roughly the
     * same length category. Used for "negative" pairs.
     */
    public static function unrelated(string $lang, string $length): string
    {
        // Re-use a different topic by deriving from the same constants
        // pool but offset — keeps the corpus self-contained.
        $base = match (true) {
            'en' === $lang && 'short' === $length => 'the central bank raised interest rates by half a point following two days '
                . 'of debate the move surprised markets and triggered a sharp drop in equity '
                . 'indices across the region',
            'en' === $lang && 'medium' === $length => 'the central bank raised interest rates by half a point following two days '
                . 'of debate the move surprised markets and triggered a sharp drop in equity '
                . 'indices across the region the chair of the bank explained that persistent '
                . 'core inflation had forced policymakers to act sooner than the previous '
                . 'guidance had suggested business associations expressed concern that the '
                . 'pace of tightening would weigh on small companies that had borrowed at '
                . 'variable rates housing markets reacted swiftly with mortgage applications '
                . 'falling by twelve percent within a week',
            'en' === $lang && 'long' === $length => 'the central bank raised interest rates by half a point following two days '
                . 'of debate the move surprised markets and triggered a sharp drop in equity '
                . 'indices across the region the chair of the bank explained that persistent '
                . 'core inflation had forced policymakers to act sooner than the previous '
                . 'guidance had suggested business associations expressed concern that the '
                . 'pace of tightening would weigh on small companies that had borrowed at '
                . 'variable rates housing markets reacted swiftly with mortgage applications '
                . 'falling by twelve percent within a week analysts noted that the bank had '
                . 'previously signalled a wait and see stance and that the abrupt shift was '
                . 'driven by an unexpected jump in services prices commentators pointed out '
                . 'that wage growth in the manufacturing sector remained subdued and that the '
                . 'labour market was cooling faster than headline numbers suggested next month '
                . 'data on retail sales and consumer confidence will be closely watched for '
                . 'further signals of slowdown the finance minister called for closer '
                . 'coordination between fiscal and monetary policy and reiterated the importance '
                . 'of structural reforms to enhance long term competitiveness',
            'fr' === $lang && 'short' === $length => 'la banque centrale a relevé ses taux directeurs d un demi point après deux '
                . 'jours de débat la décision a surpris les marchés et provoqué une forte '
                . 'baisse des indices boursiers de la région',
            'fr' === $lang && 'medium' === $length => 'la banque centrale a relevé ses taux directeurs d un demi point après deux '
                . 'jours de débat la décision a surpris les marchés et provoqué une forte '
                . 'baisse des indices boursiers de la région le président de l institution a '
                . 'expliqué qu une inflation sous jacente persistante avait contraint les '
                . 'décideurs à agir plus tôt que prévu les organisations patronales se sont '
                . 'inquiétées de l incidence de ce resserrement sur les petites entreprises '
                . 'endettées à taux variable les marchés immobiliers ont réagi rapidement '
                . 'avec une chute de douze pour cent des demandes de prêt en une semaine',
            'fr' === $lang && 'long' === $length => 'la banque centrale a relevé ses taux directeurs d un demi point après deux '
                . 'jours de débat la décision a surpris les marchés et provoqué une forte '
                . 'baisse des indices boursiers de la région le président de l institution a '
                . 'expliqué qu une inflation sous jacente persistante avait contraint les '
                . 'décideurs à agir plus tôt que prévu les organisations patronales se sont '
                . 'inquiétées de l incidence de ce resserrement sur les petites entreprises '
                . 'endettées à taux variable les marchés immobiliers ont réagi rapidement '
                . 'avec une chute de douze pour cent des demandes de prêt en une semaine '
                . 'les analystes ont rappelé que la banque avait auparavant adopté une '
                . 'posture attentiste et que le revirement abrupt avait été motivé par une '
                . 'progression inattendue des prix des services les observateurs ont noté '
                . 'que la croissance des salaires dans l industrie restait modérée et que '
                . 'le marché du travail se refroidissait plus vite que les chiffres globaux '
                . 'le suggéraient les statistiques du mois prochain sur les ventes au '
                . 'détail et la confiance des ménages seront scrutées pour confirmer le '
                . 'ralentissement le ministre des finances a plaidé pour une coordination '
                . 'plus étroite entre politique budgétaire et monétaire et rappelé '
                . 'l importance des réformes structurelles',
            'zh' === $lang && 'short' === $length => '中央银行经过两天讨论后将利率提高了半个百分点'
                . '此举令市场感到意外并引发该地区股票指数大幅下跌',
            'zh' === $lang && 'medium' === $length => '中央银行经过两天讨论后将利率提高了半个百分点'
                . '此举令市场感到意外并引发该地区股票指数大幅下跌'
                . '银行行长解释称持续的核心通胀迫使决策者比此前指引所暗示的更早采取行动'
                . '商业协会对收紧步伐将给以浮动利率借款的小型企业带来压力表示担忧'
                . '住房市场反应迅速一周内抵押贷款申请下降百分之十二',
            'zh' === $lang && 'long' === $length => '中央银行经过两天讨论后将利率提高了半个百分点'
                . '此举令市场感到意外并引发该地区股票指数大幅下跌'
                . '银行行长解释称持续的核心通胀迫使决策者比此前指引所暗示的更早采取行动'
                . '商业协会对收紧步伐将给以浮动利率借款的小型企业带来压力表示担忧'
                . '住房市场反应迅速一周内抵押贷款申请下降百分之十二'
                . '分析人士指出该行此前曾发出观望信号此次突然转变是由服务价格意外上涨推动的'
                . '评论员表示制造业工资增长仍然温和劳动力市场降温速度快于整体数据所显示的'
                . '下月零售销售和消费者信心数据将被密切关注以寻找进一步放缓的迹象'
                . '财政部长呼吁财政与货币政策更紧密协调并重申结构性改革的重要性',
            'ja' === $lang && 'short' === $length => '中央銀行は二日間の議論を経て政策金利を半パーセント引き上げた'
                . 'この決定は市場を驚かせ地域全体の株価指数の急落を引き起こした',
            'ja' === $lang && 'medium' === $length => '中央銀行は二日間の議論を経て政策金利を半パーセント引き上げた'
                . 'この決定は市場を驚かせ地域全体の株価指数の急落を引き起こした'
                . '中銀総裁は根強いコアインフレが従来の指針が示していたよりも早く政策を変更することを迫ったと説明した'
                . '経済団体は引き締めペースが変動金利で借り入れている中小企業に重荷となることを懸念した'
                . '住宅市場は迅速に反応し一週間以内に住宅ローン申請が十二パーセント減少した',
            'ja' === $lang && 'long' === $length => '中央銀行は二日間の議論を経て政策金利を半パーセント引き上げた'
                . 'この決定は市場を驚かせ地域全体の株価指数の急落を引き起こした'
                . '中銀総裁は根強いコアインフレが従来の指針が示していたよりも早く政策を変更することを迫ったと説明した'
                . '経済団体は引き締めペースが変動金利で借り入れている中小企業に重荷となることを懸念した'
                . '住宅市場は迅速に反応し一週間以内に住宅ローン申請が十二パーセント減少した'
                . 'アナリストは中銀が以前は様子見の姿勢を示しており突然の転換はサービス価格の予想外の上昇によるものであると指摘した'
                . '評論家は製造業の賃金上昇が依然として鈍く労働市場は総合指標が示すよりも速く冷え込んでいると述べた'
                . '来月の小売売上高と消費者信頼感のデータはさらなる減速の兆候を見極めるため注視される'
                . '財務大臣は財政政策と金融政策の協調強化を呼びかけ構造改革の重要性を改めて強調した',
            'ko' === $lang && 'short' === $length => '중앙은행은 이틀간의 논의 끝에 기준 금리를 반 포인트 인상했다'
                . '이번 결정은 시장을 놀라게 했고 지역 전체 주가 지수의 급락을 촉발했다',
            'ko' === $lang && 'medium' === $length => '중앙은행은 이틀간의 논의 끝에 기준 금리를 반 포인트 인상했다'
                . '이번 결정은 시장을 놀라게 했고 지역 전체 주가 지수의 급락을 촉발했다'
                . '중앙은행 의장은 지속적인 근원 인플레이션 때문에 이전 지침이 시사한 것보다 더 일찍 행동에 나서야 했다고 설명했다'
                . '재계는 긴축 속도가 변동 금리로 자금을 조달한 중소기업에 부담이 될 것이라고 우려했다'
                . '주택 시장은 빠르게 반응했으며 일주일 만에 주택 담보 대출 신청이 십이 퍼센트 감소했다',
            'ko' === $lang && 'long' === $length => '중앙은행은 이틀간의 논의 끝에 기준 금리를 반 포인트 인상했다'
                . '이번 결정은 시장을 놀라게 했고 지역 전체 주가 지수의 급락을 촉발했다'
                . '중앙은행 의장은 지속적인 근원 인플레이션 때문에 이전 지침이 시사한 것보다 더 일찍 행동에 나서야 했다고 설명했다'
                . '재계는 긴축 속도가 변동 금리로 자금을 조달한 중소기업에 부담이 될 것이라고 우려했다'
                . '주택 시장은 빠르게 반응했으며 일주일 만에 주택 담보 대출 신청이 십이 퍼센트 감소했다'
                . '분석가들은 은행이 이전에 관망하는 입장을 시사했으며 갑작스러운 전환이 서비스 물가의 예상치 못한 상승에 의한 것이라고 지적했다'
                . '평론가들은 제조업 임금 상승률이 여전히 완만하며 노동 시장이 표면 수치보다 더 빠르게 둔화되고 있다고 말했다'
                . '다음 달 소매 판매와 소비자 신뢰 지수 데이터가 추가 둔화 신호를 찾기 위해 면밀히 관찰될 것이다'
                . '재무 장관은 재정 정책과 통화 정책 간의 보다 긴밀한 조정을 요구하며 구조 개혁의 중요성을 재차 강조했다',
            default => throw new \InvalidArgumentException("Unknown language/length: {$lang}/{$length}"),
        };

        return $base;
    }
}
