package com.bens.core.merchant.repository;

import com.bens.core.common.repository.BaseRepository;
import com.bens.core.merchant.entity.Collaborator;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
import org.springframework.stereotype.Repository;

@Repository
public interface CollaboratorRepository extends BaseRepository<Collaborator> {

    Optional<Collaborator> findByEmail(String email);

    boolean existsByEmail(String email);

    List<Collaborator> findByMerchantId(UUID merchantId);
}
