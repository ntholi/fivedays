import AssessmentForm from '@/components/assessments/AssessmentForm';
import CourseWorkCard from '@/components/classes/CourseWorkCard';
import Header from '@/components/layout/Header';
import { axiosInstance } from '@/lib/config/axios';
import {
  ActionIcon,
  Container,
  Divider,
  Flex,
  Modal,
  Title,
} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconFilePlus } from '@tabler/icons-react';
import { classroom_v1 } from 'googleapis';
import { useSession } from 'next-auth/react';
import { useRouter } from 'next/router';
import React, { useEffect, useState } from 'react';

export default function ClassPage() {
  const [opened, { open, close }] = useDisclosure(false);
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
      <Modal
        opened={opened}
        onClose={close}
        title='Create Assessment'
        size='100%'
      >
        <AssessmentForm />
      </Modal>
      <Header />
      <Container size='xl' mt='md'>
        <Flex justify='space-between'>
          <Title size={23}>{router.query.name}</Title>
          <ActionIcon color='dark' onClick={open}>
            <IconFilePlus size='1.5rem' />
          </ActionIcon>
        </Flex>
        <Divider my='lg' />
        <Container>
          {assessments.map((it) => (
            <CourseWorkCard key={it.id} courseWork={it} />
          ))}
        </Container>
      </Container>
    </>
  );
}
