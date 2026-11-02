package com.bens.core.merchant.repository;

import com.bens.core.common.repository.BaseRepository;
import com.bens.core.merchant.entity.Merchant;
import java.util.Optional;
import org.springframework.stereotype.Repository;

@Repository
public interface MerchantRepository extends BaseRepository<Merchant> {

    Optional<Merchant> findByApiKeyHash(String apiKeyHash);

    boolean existsByName(String name);
}
