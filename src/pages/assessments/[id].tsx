import AssessmentCard from '@/components/classes/AssessmentCard';
import Header from '@/components/layout/Header';
import { axiosInstance } from '@/lib/config/axios';
import { Container, Divider, Title } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import { useSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React, { useEffect, useState } from 'react';

export default function ClassPage() {
  const router = useRouter();
  const { data: session } = useSession();
  const [assessments, setAssessments] = useState<
    classroom_v1.Schema$CourseWork[]
  >([]);

  useEffect(() => {
    async function getData() {
      const courseId = router.query.id;
      const response = await axiosInstance(session)?.get(
        `/courses/${courseId}/courseWork?courseWorkStates=PUBLISHED`
      );
      setAssessments(response?.data.courseWork ?? []);
    }
    if (session) {
      getData();
    }
  }, [session]);

  return (
    <>
      <Header />
      <Container size="xl" mt="md">
        <Title size={23}>{router.query.name}</Title>
        <Divider my="lg" />
        <Container>
          {assessments.map((it) => (
            <AssessmentCard key={it.id} courseWork={it} />
          ))}
        </Container>
      </Container>
    </>
  );
}
